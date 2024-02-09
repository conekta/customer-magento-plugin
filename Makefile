phpstan:
	vendor/bin/phpstan analyse api --level 5

.PHONY: zip-plugin

zip-plugin:
	$(eval VERSION=$(shell jq -r '.version' composer.json))
	@zip -r conekta_conekta_payments-$(VERSION).zip . -x "*.git*" "*.idea*" "vendor/*" "composer.lock" ".DS_Store"
