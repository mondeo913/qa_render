.PHONY: install start test diagnose stop verify

install:
	bash ./.devcontainer/install-siget.sh

start:
	bash ./INICIAR_SIGET.sh

test:
	bash ./.devcontainer/run-qa-tests.sh

diagnose:
	bash ./DIAGNOSTICAR_SIGET.sh

stop:
	bash ./DETENER_SIGET.sh

verify:
	bash ./.devcontainer/verify-siget.sh
