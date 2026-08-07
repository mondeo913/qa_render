<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if(!Schema::hasTable('permissions')||!Schema::hasTable('roles')||!Schema::hasTable('permission_role')) return;
        $now=now();
        $permissions=[
            ['scheduled_load.board','Consultar tablero Kanban de cargas','loads','Acceso al tablero operativo por dependencia y dirección.'],
            ['repository.view','Consultar repositorio institucional','repository','Acceso al administrador de archivos institucional.'],
            ['alerts.view','Consultar notificaciones','notifications','Acceso al centro de alertas y notificaciones.'],
        ];
        foreach($permissions as [$code,$name,$module,$description]) DB::table('permissions')->updateOrInsert(['code'=>$code],['name'=>$name,'module'=>$module,'description'=>$description,'created_at'=>$now,'updated_at'=>$now]);
        $map=[
            'scheduled_load.board'=>['ADMINISTRADOR','DIRECTOR_GENERAL','DIRECTOR','DIRECTOR_TRANSMISION','DIRECTOR_PROGRAMACION_CONTINUIDAD','ENLACE_INSTITUCIONAL','OPERADOR','OPERADOR_TRANSMISION','OPERADOR_PROGRAMACION_CONTINUIDAD'],
            'repository.view'=>['ADMINISTRADOR','DIRECTOR_GENERAL','DIRECTOR','DIRECTOR_TRANSMISION','DIRECTOR_PROGRAMACION_CONTINUIDAD','ENLACE_INSTITUCIONAL','OPERADOR','OPERADOR_TRANSMISION','OPERADOR_PROGRAMACION_CONTINUIDAD','FISCALIZADOR'],
            'alerts.view'=>['ADMINISTRADOR','DIRECTOR','DIRECTOR_TRANSMISION','DIRECTOR_PROGRAMACION_CONTINUIDAD','ENLACE_INSTITUCIONAL','OPERADOR','OPERADOR_TRANSMISION','OPERADOR_PROGRAMACION_CONTINUIDAD'],
        ];
        foreach($map as $permissionCode=>$roles){$pid=DB::table('permissions')->where('code',$permissionCode)->value('id');foreach(DB::table('roles')->whereIn('code',$roles)->pluck('id') as $rid)DB::table('permission_role')->updateOrInsert(['role_id'=>$rid,'permission_id'=>$pid]);}
        if(Schema::hasTable('system_settings')){
            foreach(['system.name'=>'SIGET','system.subtitle'=>'Sistema de Gestión de Evidencias de Transmisión','ui.default_theme'=>'auto'] as $key=>$value) DB::table('system_settings')->updateOrInsert(['key'=>$key],['value'=>json_encode($value),'description'=>'Configuración institucional K2','updated_at'=>$now,'created_at'=>$now]);
        }
    }
    public function down(): void {}
};
