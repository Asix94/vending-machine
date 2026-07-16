.PHONY: test test-usecases test-controllers test-buy

test:
	docker compose exec php php bin/phpunit

test-usecases:
	docker compose exec php php bin/phpunit tests/Wallet/Application tests/VendingMachine/Application

test-controllers:
	docker compose exec php php bin/phpunit tests/Controller

test-buy:
	docker compose exec php php bin/phpunit tests/Controller/BuyProductControllerTest.php
