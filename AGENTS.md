# Development Guidelines

## Project Structure

- Magento 2 payment module: `conekta/conekta_payments`
- PHP versions: 8.2, 8.3, 8.4
- Tests: `Test/Unit/`
- Stubs for Magento generated classes: `Test/Stub/`
- CI: `.github/workflows/tests.yml`

## Running Tests

```bash
vendor/bin/phpunit
```

## Running Static Analysis

```bash
vendor/bin/phpstan analyse --no-progress --memory-limit=512M
```

## Testing Conventions

### Magento Factory Classes

Magento auto-generates factory classes (e.g., `RawFactory`, `JsonFactory`) that don't exist in vendor. Create stub classes in `Test/Stub/` and load them via `Test/bootstrap.php`:

```php
// Test/Stub/RawFactory.php
namespace Magento\Framework\Controller\Result;
class RawFactory {
    public function create() { return null; }
}
```

### Mocking Magento Request

`RequestInterface` doesn't include `getMethod`, `getContent`, `getPathInfo`, `setControllerName`, or `setAlias`. Use `addMethods` for those and `getMockForAbstractClass`:

```php
$request = $this->getMockBuilder(RequestInterface::class)
    ->addMethods(['getMethod', 'getContent'])
    ->getMockForAbstractClass();
```

### Mocking Magento Quote

`Quote` uses magic getters/setters. Methods like `setCustomerEmail` and `getCustomerEmail` need `addMethods`, while `setStoreId`, `getPayment`, etc. use `onlyMethods`:

```php
$quote = $this->getMockBuilder(Quote::class)
    ->disableOriginalConstructor()
    ->addMethods(['setCustomerEmail', 'getCustomerEmail'])
    ->onlyMethods(['getPayment', 'setStoreId', ...])
    ->getMock();
```

### Classes Using ObjectManager

For classes that call `ObjectManager::getInstance()` in the constructor (e.g., `MissingOrders`), use `ReflectionClass::newInstanceWithoutConstructor()` and inject mocks via reflection:

```php
$reflection = new ReflectionClass(MyClass::class);
$instance = $reflection->newInstanceWithoutConstructor();

$prop = $reflection->getProperty('myDependency');
$prop->setAccessible(true);
$prop->setValue($instance, $mock);
```

### Assertions

Every test must have explicit `assert*` calls. Do not rely solely on mock expectations (`expects`). Capture values via callbacks and assert on them:

```php
$capturedCode = null;
$mock->method('setHttpResponseCode')->willReturnCallback(function ($code) use (&$capturedCode, $mock) {
    $capturedCode = $code;
    return $mock;
});

$controller->execute();

$this->assertSame(200, $capturedCode);
```

### Return Types on Mocks

When a mocked method has a non-nullable return type, return a mock of that type, not `null`:

```php
// updateCharge returns ChargeResponse
$chargeResponse = $this->createMock(ChargeResponse::class);
$mock->method('updateCharge')->willReturn($chargeResponse);
```

## PHPStan Conventions

### Baseline

Preexisting errors are tracked in `phpstan-baseline.neon`. After fixing errors, regenerate:

```bash
vendor/bin/phpstan analyse --no-progress --memory-limit=512M --generate-baseline
```

Never edit the baseline manually.

### Ignore Patterns

Only ignore errors from Magento internals in `phpstan.neon` (generated factories, interface method mismatches). Errors in project code should be fixed or stay in the baseline.

### PHPDoc Types

Use `@return array` instead of `@return \array[][]` (invalid type). Keep PHPDoc types aligned with actual return values.

### Fixing Interface Mismatches

When project interfaces are missing methods that concrete classes implement (e.g., `loadByConektaOrderId`), add the method to the interface instead of ignoring the error.

## Webhook Controller Patterns

### All Error Responses Must Return JSON

Every error path (400, 404, 500) must return a JSON body with `error` and `message` fields. Success (200) returns no body.

### Catch Both Exception and Throwable

Use separate catch blocks for `EntityNotFoundException`, `\Exception`, and `\Throwable` to ensure PHP fatal errors (TypeError, Error) always return a JSON 500 response.

## CI/CD

### GitHub Actions

- Matrix: PHP 8.2, 8.3, 8.4
- Uses `--ignore-platform-reqs` for `composer install` (Magento framework version constraints)
- Requires `MAGENTO_PUBLIC_KEY` and `MAGENTO_PRIVATE_KEY` secrets for `repo.magento.com`
- Runs PHPStan before PHPUnit
