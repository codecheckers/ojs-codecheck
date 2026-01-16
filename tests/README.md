# CODECHECK Plugin Tests

## Overview
This directory contains comprehensive test coverage for all PHP classes in the CODECHECK plugin.

## Test Statistics
- **Total Tests:** 166
- **Unit Tests (Run Locally):** 139 ✅
- **Integration Tests (Require Full OJS):** 27 ⚠️
- **Test Coverage:** All classes in `classes/*` directory

---

## Test Organization

### Unit Tests (Pure Logic Testing)
These tests run in any environment without dependencies:

#### ✅ **Core Classes**
- `ConstantsUnitTest.php` - Plugin constants validation

#### ✅ **Submission Classes**
- `CodecheckSubmissionUnitTest.php` - Data object methods and getters
- `CodecheckSubmissionDAOUnitTest.php` - DAO structure and method signatures
- `CodecheckMetadataDAOUnitTest.php` - Metadata DAO structure and logic
- `SchemaUnitTest.php` - Schema field additions

#### ✅ **Settings Classes**
- `ActionsUnitTest.php` - Action structure and signatures (partial)
- `ManageUnitTest.php` - Manage structure and signatures (partial)
- `SettingsFormUnitTest.php` - Form structure and signatures (partial)

#### ✅ **Workflow Classes**
- `CodecheckMetadataHandlerUnitTest.php` - Handler method signatures and structure

#### ✅ **Frontend Classes**
- `ArticleDetailsUnitTest.php` - Article detail methods and signatures (partial)

#### ✅ **Migration Classes**
- `CodecheckSchemaMigrationUnitTest.php` - Migration structure validation

#### ✅ **Main Plugin**
- `CodecheckPluginUnitTest.php` - Plugin core methods and hooks (partial)

---

### Integration Tests (Require Full OJS Environment)
These tests are **skipped** in local environments and require:
- Full OJS installation with all dependencies
- Database connection
- Laravel facades initialized
- Translation system (`__()` function)
- Template rendering system

**Skipped Test Categories:**
1. **Translation-dependent tests** (16 tests)
   - Actions with `__()` calls
   - Settings forms with localization
   - Plugin checkbox with translated labels

2. **Laravel Facade tests** (9 tests)
   - SettingsForm instantiation
   - Manage execute methods
   - Database facade operations

3. **Template rendering tests** (2 tests)
   - ArticleDetails with TemplateManager
   - Frontend display generation

---

## Running Tests

### Local Development (Mac/Linux/Windows)

From the plugin root directory:
```bash
cd plugins/generic/codecheck
php ../../../lib/pkp/lib/vendor/bin/phpunit --configuration phpunit.xml tests/
```

**Expected Output:**
```
Tests: 166, Assertions: 275, Skipped: 27
OK, but there were issues!
```

### Docker/CI Environment

From OJS root with full environment:
```bash
# If using provided runTests.sh
sh plugins/generic/codecheck/tests/runTests.sh

# Or directly
lib/pkp/lib/vendor/phpunit/phpunit/phpunit \
    -c lib/pkp/tests/phpunit.xml \
    plugins/generic/codecheck/tests/
```

**Expected Output (in full environment):**
```
Tests: 166, Assertions: 300+ (all passing with full environment)
```

---

## Test Files Structure
```
tests/
├── bootstrap.php                          # PHPUnit bootstrap loader
├── PKPTestCase.php                        # Base test case class
├── phpunit.xml                            # PHPUnit configuration
├── runTests.sh                            # Test runner script for Docker/CI
├── CodecheckPluginUnitTest.php           # Main plugin tests
├── ConstantsUnitTest.php                 # Constants tests
├── FrontEndUnitTests/
│   └── ArticleDetailsUnitTest.php
├── MigrationUnitTests/
│   └── CodecheckSchemaMigrationUnitTest.php
├── SettingsUnitTests/
│   ├── ActionsUnitTest.php
│   ├── ManageUnitTest.php
│   └── SettingsFormUnitTest.php
├── SubmissionUnitTests/
│   ├── CodecheckMetadataDAOUnitTest.php
│   ├── CodecheckSubmissionDAOUnitTest.php
│   ├── CodecheckSubmissionUnitTest.php
│   └── SchemaUnitTest.php
└── WorkflowUnitTests/
    └── CodecheckMetadataHandlerUnitTest.php
```

---

## Development Guidelines

### Writing New Tests

1. **Extend PKPTestCase:**
```php
   use PKP\tests\PKPTestCase;
   
   class YourNewTest extends PKPTestCase
   {
       protected function setUp(): void
       {
           parent::setUp();
       }
   }
```

2. **For Integration Tests:**
   If your test requires full OJS environment, mark it:
```php
   public function testSomethingWithOJS()
   {
       $this->markTestSkipped('Requires full OJS environment');
       // ... test code
   }
```

3. **Naming Conventions:**
   - Test classes: `ClassNameUnitTest.php`
   - Test methods: `testMethodNameBehaviorExpected()`
   - Be descriptive: `testGetSubmissionIdReturnsInteger()` ✅
   - Not: `testGetSubmissionId()` ❌

### Test Coverage Goals

- ✅ All public methods have tests
- ✅ Edge cases covered (null, empty, invalid input)
- ✅ Return types validated
- ✅ Method signatures documented
- ⚠️ Integration scenarios documented (even if skipped locally)

---

## Troubleshooting

### "Class PKP\tests\PKPTestCase not found"
- Make sure `bootstrap.php` loads `PKPTestCase.php`
- Check that `phpunit.xml` has correct bootstrap path

### "Database error" messages in output
- These are **expected** in unit tests
- They're from mocked database calls
- Not actual errors (tests still pass)

### "Translator not found" errors
- These tests need full OJS environment
- Should be marked with `markTestSkipped()`
- Run in Docker/CI for complete testing

### PHPUnit not found
```bash
cd ../../../lib/pkp
composer require --dev phpunit/phpunit
```

---

## Contributing

When adding new classes to the plugin:
1. Create corresponding unit test file
2. Test all public methods
3. Test edge cases and error handling
4. Mark integration tests appropriately
5. Update this README if needed

---

## CI/CD Integration

For automated testing in CI/CD pipelines:
```yaml
# Example: .github/workflows/php-tests.yml
name: PHP Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - name: Install dependencies
        run: |
          cd lib/pkp
          composer install
      - name: Run tests
        run: |
          cd plugins/generic/codecheck
          php ../../../lib/pkp/lib/vendor/bin/phpunit tests/
```