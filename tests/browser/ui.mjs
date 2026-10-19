import { createRequire } from 'node:module';
import { readFileSync, mkdirSync } from 'node:fs';
import assert from 'node:assert/strict';
const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const base = process.env.SIGET_UI_URL || 'http://127.0.0.1:8000';
const password = process.env.SIGET_QA_PASSWORD || 'SigetQA_2026_Cambiar!';
const users = JSON.parse(readFileSync('storage/framework/testing/ui-users.json', 'utf8'));
const screenshots = 'storage/framework/testing/ui-screenshots';
mkdirSync(screenshots, { recursive: true });
const browser = await chromium.launch({ headless: true, ...(process.env.SIGET_CHROMIUM_PATH ? { executablePath: process.env.SIGET_CHROMIUM_PATH } : {}) });
let pages = 0;
async function checkPage(page, name) {
  await page.waitForFunction(() => Boolean(window.SigetAppearance));
  await page.waitForTimeout(150);
  const geometry = await page.evaluate(() => ({ width: innerWidth, scroll: document.documentElement.scrollWidth }));
  assert(geometry.scroll <= geometry.width + 2, `${name}: horizontal overflow ${geometry.scroll}/${geometry.width}`);
  assert.equal(await page.locator('script[src*="siget-zenith-runtime.js"]').count(), 1, `${name}: duplicate runtime`);
  assert.equal(await page.locator('link[href*="siget-zenith-runtime.css"]').count(), 0, `${name}: duplicate visual CSS`);
  assert.equal(await page.locator('.siget-main').count(), 1);
  const clipped = await page.locator('.exec-kpi label').evaluateAll(elements => elements.filter(el => {
    const label = el.getBoundingClientRect(), card = el.closest('.exec-kpi').getBoundingClientRect();
    return label.width < 70 || label.bottom > card.bottom || label.right > card.right;
  }).map(el => el.textContent));
  assert.deepEqual(clipped, [], `${name}: clipped KPI labels`);
  pages++;
}
async function checkDashboard(page, role) {
  const profiles = {ADMINISTRADOR:'admin', DIRECTOR_GENERAL:'executive', ENLACE_INSTITUCIONAL:'institutional',
    DIRECTOR_TRANSMISION:'direction', DIRECTOR_PROGRAMACION_CONTINUIDAD:'direction',
    OPERADOR_TRANSMISION:'operator', OPERADOR_PROGRAMACION_CONTINUIDAD:'operator', FISCALIZADOR:'fiscal'};
  assert.equal(await page.locator('.siget-dashboard').getAttribute('data-dashboard-profile'), profiles[role]);
  assert.equal(await page.locator('.siget-dashboard-kpi').count(), 4);
  assert(await page.locator('.siget-dashboard-hero h2').evaluate(el=>parseFloat(getComputedStyle(el).fontSize)>=24),'Dashboard heading is too small');
  await page.waitForFunction(() => [...document.querySelectorAll('.siget-dashboard script[data-siget-chart]')]
    .every(node => document.getElementById(node.dataset.sigetChart)?.dataset.chartReady === 'true'));
  const figures = await page.locator('.siget-dashboard script[data-siget-chart]').evaluateAll(nodes => nodes.map(node => {
    const config = JSON.parse(node.textContent), canvas = document.getElementById(node.dataset.sigetChart);
    const pixels = canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height).data;
    return {id:canvas.id, config, colors:JSON.parse(canvas.dataset.chartColors), painted:pixels.some((v,i) => i%4===3 && v>0)};
  }));
  const expected = Number(await page.locator('.siget-dashboard').getAttribute('data-expected'));
  assert.equal(Number(await page.locator('.siget-dashboard').getAttribute('data-status-total')), expected);
  for (const figure of figures) {
    assert(figure.painted, role + ': blank canvas ' + figure.id);
    assert.deepEqual(figure.colors, figure.config.datasets.map(d => d.backgroundColor), role + ': state colors changed');
    for (const dataset of figure.config.datasets) assert.equal(dataset.data.length, figure.config.labels.length);
    if (figure.config.type === 'doughnut') assert.equal(figure.config.datasets[0].data.reduce((a,b)=>a+b,0),expected);
  }
  if (['direction','operator'].includes(profiles[role])) assert.equal(await page.getByRole('link',{name:'Exportar resumen',exact:true}).count(),0);
  assert(await page.locator('.siget-dashboard-kpi h3').first().evaluate(el => parseFloat(getComputedStyle(el).fontSize)>=14));
  return figures.map(f => ({id:f.id,colors:f.colors}));
}
try {
  for (const user of users) {
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, colorScheme: 'light' });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('response', response => { if (response.status() >= 400 && !response.url().endsWith('favicon.ico')) errors.push(`HTTP ${response.status()}: ${response.url()}`); });
    await page.goto(base + '/iniciar-sesion');
    await page.locator('[name="email"]').fill(user.email);
    await page.locator('[name="password"]').fill(password);
    await Promise.all([page.waitForURL('**/dashboard'), page.locator('button[type="submit"], button.btn-primary').last().click()]);
    await checkPage(page, user.email);
    const dashboardColors = await checkDashboard(page, user.role);
    await page.getByRole('button', { name: 'Elegir tema', exact: true }).click();
    assert(await page.locator('.siget-top-actions .dropdown-menu.show').count() > 0, 'Bootstrap dropdown failed');
    await page.locator('.siget-top-actions [data-theme-value="dark"]').click();
    assert.equal(await page.locator('html').getAttribute('data-bs-theme'), 'dark');
    assert.deepEqual(await checkDashboard(page,user.role),dashboardColors,'State colors must survive theme changes');
    await page.getByRole('button', { name: 'Personalizar apariencia', exact: true }).click();
    assert.equal(await page.locator('#siget-customizer').getAttribute('aria-hidden'), 'false');
    for (const [kind, value] of [['preset', 'ocean'], ['density', 'comfortable'], ['container', 'boxed'], ['radius', 'soft'], ['layout', 'topnav']]) {
      await page.locator(`#siget-customizer [data-zenith-${kind}="${value}"]`).click();
      assert.equal(await page.locator('html').getAttribute(`data-zenith-${kind}`), value);
    }
    assert.equal(await page.locator('.siget-main').evaluate(el => getComputedStyle(el).marginLeft), '0px');
    await page.locator('#siget-customizer [data-zenith-layout="sidebar"]').click();
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('#siget-customizer').getAttribute('aria-hidden'), 'true');
    await page.reload();
    assert.equal(await page.locator('html').getAttribute('data-bs-theme'), 'dark', 'Theme did not persist');
    assert.equal(await page.locator('html').getAttribute('data-zenith-preset'), 'ocean', 'Color did not persist');
    assert.deepEqual(await checkDashboard(page,user.role),dashboardColors,'State colors must survive palette changes');
    await page.getByRole('button', { name: 'Alternar menú de navegación' }).click();
    await page.waitForFunction(() => document.querySelector('.siget-sidebar').getBoundingClientRect().width === 72);
    assert.equal(await page.locator('.siget-sidebar').evaluate(el => el.getBoundingClientRect().width), 72, 'Sidebar collapse failed');
    await page.getByRole('button', { name: 'Alternar menú de navegación' }).click();
    await page.waitForTimeout(1800);
    await page.screenshot({ path: `${screenshots}/${user.id}-dashboard-dark.png`, fullPage: true });
    if (process.env.SIGET_UI_VISUAL_EVIDENCE === 'true' && user.role === 'ADMINISTRADOR') console.log('SIGET_UI_IMAGE_DASHBOARD:' + (await page.screenshot({ type: 'jpeg', quality: 55 })).toString('base64'));
    assert.equal(await page.evaluate(() => localStorage.getItem(`siget-ui:${document.documentElement.dataset.sigetUser}:theme`)), 'dark', 'Preferences must be scoped by account');
    if (user.role === 'ADMINISTRADOR') {
      await page.goto(await page.locator('.siget-nav a[href$="/admin/usuarios"]').getAttribute('href'));
      if (await page.locator('.siget-admin-users').count()) {
        const text = await page.locator('.siget-admin-users').innerText();
        assert(!text.includes('.IPAB@siget.local'), 'Agency-specific accounts still visible');
        const linkRow = page.locator('tr[id^="user-row-"]').filter({hasText: 'enlace@siget.local'});
        const editId = (await linkRow.getAttribute('id')).replace('user-row-', 'user-edit-');
        await linkRow.getByRole('button', {name: 'Editar', exact: true}).click();
        const editForm = page.locator(`#${editId} form`);
        const originalName = await editForm.locator('[name="name"]').inputValue();
        const saveEdit = async () => Promise.all([
          page.waitForNavigation({waitUntil: 'domcontentloaded'}),
          editForm.getByRole('button', {name: 'Guardar', exact: true}).click()
        ]);
        await editForm.locator('[name="email"]').fill('enlace_institucional@');
        assert.equal(await editForm.locator('[name="email"]').evaluate(el => el.validity.valid), false, 'Incomplete login must be rejected');
        await editForm.locator('[name="email"]').fill('admin@siget.local');
        await saveEdit();
        assert(await page.locator(`#${editId}`).isVisible(), 'Validation must keep the edit form open');
        assert((await page.locator('.siget-content').innerText()).includes('Este correo de acceso ya pertenece a otro usuario.'));
        await editForm.locator('[name="name"]').fill('Enlace institucional actualizado');
        await editForm.locator('[name="email"]').fill('enlace_institucional@siget.local');
        await saveEdit();
        await page.locator('tr[id^="user-row-"]').filter({hasText: 'enlace_institucional@siget.local'}).getByRole('button', {name: 'Editar', exact: true}).click();
        assert.equal(await editForm.locator('[name="name"]').inputValue(), 'Enlace institucional actualizado');
        // Restore fixtures before the role-by-role login checks.
        await editForm.locator('[name="name"]').fill(originalName);
        await editForm.locator('[name="email"]').fill('enlace@siget.local');
        await saveEdit();
        assert(await page.getByText('enlace@siget.local', {exact: true}).isVisible());

      }
      await page.goto(base + '/dashboard');
    }
    // Dashboard filtering keeps role scope and clears the previous dependency's pauta.
    const dashboardAgencies = await page.locator('#siget-agency-filter option:not([value=""])').evaluateAll(nodes=>nodes.map(n=>n.value));
    if (dashboardAgencies.length) {
      await Promise.all([page.waitForNavigation(),page.locator('#siget-agency-filter').selectOption(dashboardAgencies[0])]);
      assert.equal(await page.locator('#siget-agency-filter').inputValue(),dashboardAgencies[0]);
      await checkDashboard(page,user.role);
      if (dashboardAgencies.length>1) {
        const pautaOptions=await page.locator('#siget-pauta-filter option:not([value=""])').evaluateAll(nodes=>nodes.map(n=>n.value));
        if (pautaOptions.length) await page.locator('#siget-pauta-filter').selectOption(pautaOptions[0]);
        await Promise.all([page.waitForNavigation(),page.locator('#siget-agency-filter').selectOption(dashboardAgencies[1])]);
        assert.equal(await page.locator('#siget-pauta-filter').inputValue(),'');
        await checkDashboard(page,user.role);
      }
      await page.goto(base+'/dashboard');
    }
    if (process.env.SIGET_UI_VISUAL_EVIDENCE === 'true' && user.role==='DIRECTOR_TRANSMISION') {
      await checkDashboard(page,user.role);
      await page.waitForTimeout(650);
      console.log('SIGET_UI_IMAGE_DIRECTOR:'+(await page.screenshot({type:'jpeg',quality:65})).toString('base64'));
    }
    // Every menu entry is a GET page already authorized by this user's role.
    const links = await page.locator('.siget-nav a').evaluateAll(elements => elements.map(el => el.href));
    for (const url of links) {
      const response = await page.goto(url);
      assert.equal(response.status(), 200, `${user.email}: menu route ${url}`);
      await checkPage(page, `${user.email}: ${url}`);
      // Real Bootstrap collapse controls, where present, must open and close.
      const collapse = page.locator('[data-bs-toggle="collapse"]').first();
      if (await collapse.count() && await collapse.isVisible()) {
        const selector = await collapse.getAttribute('data-bs-target') || await collapse.getAttribute('href');
        if (selector?.startsWith('#')) {
          const before = await page.locator(selector).evaluate(el => el.classList.contains('show'));
          await collapse.click();
          await page.waitForTimeout(500);
          assert.notEqual(await page.locator(selector).evaluate(el => el.classList.contains('show')), before, 'Bootstrap collapse failed');
        }
      }
    }
    if (links.some(url => new URL(url).pathname === '/calendario')) {
      await page.goto(base + '/calendario');
      if (['ADMINISTRADOR', 'ENLACE_INSTITUCIONAL'].includes(user.role)) {
        assert.equal(await page.locator('#calendarAgency option[value]:not([value=""])').count(), 7, 'Calendar must include agencies without prior loads');
        assert.equal(await page.locator('select[name="contracting_agency_id"] option:not([value=""])').count(), 7, 'Import must include authorized agencies without prior loads');
      }
      const options = await page.locator('#calendarAgency option:not([value=""])').evaluateAll(els => els.map(el => ({id: el.value, name: el.textContent})));
      if (options.length) {
        const allEvents = await (await context.request.get(base + '/calendario/eventos?start=2026-01-01&end=2027-01-01')).json();
        const sample = allEvents[0];
        const selected = options.find(option => Number(option.id) === sample?.extendedProps.contracting_agency_id) || options[0];
        const initialDate = sample?.start.slice(0, 10) || '2026-07-07';
        const [programmed, eventResponse] = await Promise.all([
          page.waitForResponse(response => response.url().includes('/calendario/fechas-programadas') && response.url().includes('contracting_agency_id=' + selected.id)),
          page.waitForResponse(response => response.url().includes('/calendario/eventos') && response.url().includes('contracting_agency_id=' + selected.id)),
          page.goto(base + '/calendario?date=' + initialDate + '&contracting_agency_id=' + selected.id)
        ]);
        assert.equal(await page.locator('#calendarAgency').inputValue(), selected.id);
        const dates = (await programmed.json()).dates;
        await page.waitForFunction(expected => [...document.querySelectorAll('.fc-daygrid-day[data-date]')].every(cell => cell.classList.contains('fc-day-disabled-by-siget') === !expected.includes(cell.dataset.date)), dates);
        assert((await page.locator('.fc-toolbar-title').innerText()).includes('2026'));
        await checkPage(page, `${user.email}: calendar agency filter`);
        const events = await eventResponse.json();
        if (events.length) {
          const event = events[0];
          const card = page.locator(`[data-load-id="${event.id}"]`).first();
          await card.waitFor({state: 'visible'});
          const rgb = event.backgroundColor.match(/[a-f0-9]{2}/gi).map(hex => parseInt(hex, 16));
          for (const theme of ['dark', 'light']) {
            await page.getByRole('button', {name: 'Elegir tema', exact: true}).click();
            await page.locator(`.siget-top-actions [data-theme-value="${theme}"]`).click();
            assert.equal(await card.evaluate(el => getComputedStyle(el).backgroundColor), `rgb(${rgb.join(', ')})`, 'Calendar event lost its state color in ' + theme);
            assert.equal(await card.locator('.siget-calendar-state').innerText(), event.extendedProps.appearance.label);
            if (process.env.SIGET_UI_VISUAL_EVIDENCE === 'true' && user.role === 'OPERADOR_TRANSMISION') {
              await page.screenshot({path: `${screenshots}/calendar-${theme}.png`, fullPage: true});
              console.log('SIGET_UI_IMAGE_CALENDAR_' + theme.toUpperCase() + ':' + (await page.screenshot({type: 'jpeg', quality: 65})).toString('base64'));
            }
          }
          await page.getByRole('button', {name: 'Elegir tema', exact: true}).click();
          await page.locator('.siget-top-actions [data-theme-value="dark"]').click();
        }

      }
    }
    if (links.some(url => new URL(url).pathname === '/reportes')) {
      const types = ['executive', 'compliance', 'pending', 'evidence', 'audit', ...(user.role === 'ADMINISTRADOR' ? ['builder'] : [])];
      for (const width of [1440, 768, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        for (const type of types) {
          await page.goto(base + '/reportes?report=' + type);
          await checkPage(page, `${user.email}: reports ${type} ${width}`);
          assert(await page.locator('.report-hero h2').evaluate(el => parseFloat(getComputedStyle(el).fontSize) >= 22), 'Report heading is too small');
          assert.equal(await page.locator('.report-nav').evaluate(el => getComputedStyle(el).display), 'flex', 'Report navigation is inline');
          const th = page.locator('.report-table th').first();
          if (await th.count()) assert(await th.evaluate(el => parseFloat(getComputedStyle(el).fontSize) >= 14), 'Report table heading is too small');
          if (type === 'executive') {
            assert.equal(await page.locator('.report-kpis').evaluate(el => getComputedStyle(el).display), 'grid');
            assert.equal(await page.locator('.report-kpi').first().evaluate(el => getComputedStyle(el).flexDirection), 'column');
          }
          if (type === 'builder') {
            const before = await page.locator('#builderPreviewColumns .report-chip').count();
            await page.locator('.builder-column').first().uncheck();
            assert.equal(await page.locator('#builderPreviewColumns .report-chip').count(), before - 1, 'Template capsules did not update');
          }
          if (user.role === 'ADMINISTRADOR' && width === 1440 && ['executive', 'builder'].includes(type)) {
            await page.screenshot({ path: `${screenshots}/reports-${type}.png`, fullPage: true });
            if (process.env.SIGET_UI_VISUAL_EVIDENCE === 'true') console.log('SIGET_UI_IMAGE_REPORT_' + type.toUpperCase() + ':' + (await page.screenshot({ type: 'jpeg', quality: 65 })).toString('base64'));
          }
        }
      }
      await page.setViewportSize({ width: 1440, height: 1000 });
      await page.goto(base + '/reportes?report=executive');
      await Promise.all([page.waitForURL(url => url.searchParams.has('from')), page.locator('[data-report-range="month"]').click()]);
      assert(new URL(page.url()).searchParams.get('to'), 'Monthly filter failed');
      if (user.role === 'ADMINISTRADOR') {
        for (const [label, prefix] of [['pdf', '%PDF'], ['xlsx', 'PK'], ['csv', null]]) {
          const link = page.locator(`.report-actions a[href*="/reportes/${label}"]`);
          const response = await context.request.get(await link.getAttribute('href'));
          assert.equal(response.status(), 200, `${label} export failed`);
          const bytes = await response.body();
          assert(bytes.length > 20);
          if (prefix) assert.equal(bytes.subarray(0, prefix.length).toString(), prefix);
        }
      }
    }
    await page.goto(base + '/repositorio');
    const repositoryAgencies=await page.locator('[data-repository-agency-id]').evaluateAll(nodes=>nodes.map(n=>n.dataset.repositoryAgencyId));
    const repositoryFolders=await page.locator('[data-repository-folder-id]').evaluateAll(nodes=>nodes.map(n=>({id:n.dataset.repositoryFolderId,loads:Number(n.dataset.loadCount)})));
    assert.deepEqual(repositoryFolders.map(f=>f.id),repositoryAgencies,'Folder catalog must match authorized dependencies, including empty ones');
    if (user.role!=='FISCALIZADOR') assert.equal(repositoryFolders.length,7,'Repository must include all seven authorized dependencies');
    const emptyFolder=repositoryFolders.find(folder=>folder.loads===0);
    if (emptyFolder) {
      await Promise.all([page.waitForNavigation(),page.locator(`[data-repository-folder-id="${emptyFolder.id}"]`).click()]);
      assert.equal(await page.locator('[data-repository-folder-id]').count(),1,'Selected dependency must hide unrelated folder cards');
      assert.equal(await page.locator('[data-repository-folder-id]').getAttribute('data-repository-folder-id'),emptyFolder.id);
      assert.equal(await page.locator('[data-repository-empty]').count(),1,'Empty dependency must explain its available folder');
      assert.equal(await page.locator('[data-repository-file-id]').count(),0,'Empty dependency must not display files from other agencies');
      assert.equal(await page.locator('[data-repository-agency-id]').count(),repositoryAgencies.length);
      await checkPage(page,`${user.email}: empty repository dependency`);
      if (process.env.SIGET_UI_VISUAL_EVIDENCE==='true' && user.role==='OPERADOR_PROGRAMACION_CONTINUIDAD') console.log('SIGET_UI_IMAGE_REPOSITORY_EMPTY_OPERATOR:'+(await page.screenshot({type:'jpeg',quality:65})).toString('base64'));
      await page.getByRole('link',{name:'Ver todas las dependencias',exact:true}).click();
    }
    const populatedFolder=repositoryFolders.find(folder=>folder.loads>0);
    if (populatedFolder) {
      await Promise.all([page.waitForNavigation(),page.locator(`[data-repository-folder-id="${populatedFolder.id}"]`).click()]);
      assert.equal(await page.locator('[data-repository-folder-id]').count(),1);
      assert.equal(await page.locator('[data-repository-load-id]').count(),populatedFolder.loads,'Every accessible programmed date needs a link');
      const foreignFiles=await page.locator('[data-repository-file-id]').evaluateAll((nodes,id)=>nodes.filter(n=>n.dataset.agencyId!==id).map(n=>n.dataset.repositoryFileId),populatedFolder.id);
      assert.deepEqual(foreignFiles,[],'Recent files must follow dependency selection');
      if (['OPERADOR_TRANSMISION','OPERADOR_PROGRAMACION_CONTINUIDAD','DIRECTOR_TRANSMISION','DIRECTOR_PROGRAMACION_CONTINUIDAD'].includes(user.role)) assert.equal(await page.locator('[data-repository-report]').count(),0,'Unauthorized exports must be hidden');
      await checkPage(page,`${user.email}: populated repository dependency`);
      await page.getByRole('link',{name:'Ver todas las dependencias',exact:true}).click();
    }
    await page.locator('[data-file-view="list"]').click();
    assert(await page.locator('[data-file-manager]').evaluate(el => el.classList.contains('siget-file-list-view')), 'Repository list failed');
    await page.locator('[data-file-view="grid"]').click();
    assert.equal(await page.locator('.siget-folder-grid').first().evaluate(el => getComputedStyle(el).display), 'grid', 'Repository grid is missing');
    if (await page.locator('.siget-folder-card').count()) assert.equal(await page.locator('.siget-folder-card').first().evaluate(el => getComputedStyle(el).display), 'flex', 'Repository cards are inline');
    await page.screenshot({ path: `${screenshots}/${user.id}-repository-dark.png`, fullPage: true });
    if (process.env.SIGET_UI_VISUAL_EVIDENCE === 'true' && user.role === 'ADMINISTRADOR') console.log('SIGET_UI_IMAGE_REPOSITORY:' + (await page.screenshot({ type: 'jpeg', quality: 55 })).toString('base64'));
    await page.goto(base + '/tablero-cargas');
    if (['OPERADOR_TRANSMISION', 'OPERADOR_PROGRAMACION_CONTINUIDAD'].includes(user.role)) {
      const pautas = await page.locator('#boardPauta option:not([value=""])').evaluateAll(options => options.map(option => ({id: option.value, agency: option.dataset.agencyId})));
      if (pautas.length) {
        await Promise.all([
          page.waitForURL(url => url.searchParams.get('pauta_id') === pautas[0].id),
          page.locator('#boardPauta').selectOption(pautas[0].id)
        ]);
        assert.equal(await page.locator('#boardPauta').inputValue(), pautas[0].id);
        assert.equal(await page.locator('#boardAgency option:not([value=""])').count(), 7, 'Pauta selection must not shrink the agency catalog');
        const other = pautas.find(pauta => pauta.agency !== pautas[0].agency);
        if (other) {
          await Promise.all([
            page.waitForURL(url => url.searchParams.get('agency_id') === other.agency && !url.searchParams.get('pauta_id')),
            page.locator('#boardAgency').selectOption(other.agency)
          ]);
          assert(await page.locator(`#boardPauta option[value="${other.id}"]`).count(), 'Switching agency lost its pautas');
          assert.equal(await page.locator('#boardPauta').inputValue(), '');
          assert(!new URL(page.url()).searchParams.get('from'), 'Old period must be cleared');
        }
        await checkPage(page, `${user.email}: board filter navigation`);
      }
    }
    await page.screenshot({ path: `${screenshots}/${user.id}-board-dark.png`, fullPage: true });
    if (process.env.SIGET_UI_VISUAL_EVIDENCE === 'true' && user.role === 'ADMINISTRADOR') console.log('SIGET_UI_IMAGE_BOARD:' + (await page.screenshot({ type: 'jpeg', quality: 55 })).toString('base64'));
    for (const width of [768, 390]) {
      await page.setViewportSize({ width, height: 844 });
      for (const route of ['/dashboard', '/repositorio', '/tablero-cargas']) {
        await page.goto(base + route);
        await checkPage(page, `${user.email}: ${width} ${route}`);
        if (route==='/dashboard') await checkDashboard(page,user.role);
        await page.getByRole('button', { name: 'Alternar menú de navegación' }).click();
        await page.waitForFunction(() => Math.round(document.querySelector('.siget-sidebar').getBoundingClientRect().left) === 0);
        assert.equal(await page.locator('.siget-sidebar').evaluate(el => Math.round(el.getBoundingClientRect().left)), 0, 'Mobile navigation failed');
        await page.keyboard.press('Escape');
        await page.waitForFunction(() => document.querySelector('.siget-sidebar').getBoundingClientRect().right <= 1);
        assert(await page.locator('.siget-sidebar').evaluate(el => el.getBoundingClientRect().right <= 1), 'Mobile navigation did not close');
      }
    }
    await page.screenshot({ path: `${screenshots}/${user.id}-mobile.png`, fullPage: true });
    assert.deepEqual(errors, [], `${user.email}: browser errors`);
    console.log(`UI PASS ${user.role} ${user.email}`);
    await context.close();
  }
  console.log(`UI ACCEPTANCE: ${users.length} accounts, ${pages} pages, desktop/tablet/mobile, no browser errors.`);
} finally { await browser.close(); }
