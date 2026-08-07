.PHONY: install start test diagnose stop verify

install:
	bash ./INSTALAR_Y_LEVANTAR_SIGET.sh

start:
	bash ./INICIAR_SIGET.sh

test:
	bash ./PROBAR_SIGET.sh

diagnose:
	bash ./DIAGNOSTICAR_SIGET.sh

stop:
	bash ./.devcontainer/stop-siget.sh

verify:
	bash ./verificar-paquete.sh
