1. **Development Guidelines**:

- Use PHP 8.3+ features where appropriate
- Follow Laravel conventions and best practices
- Implement a default Pint configuration for code styling
- Focus on creating code that provides excellent developer experience (DX), better autocompletion, type safety, and
  comprehensive docblocks
- File names: Use PascalCase (e.g., MyClass.php)
- Class and Enum names: Use PascalCase (e.g., MyClass)
- Method and variable names: Use camelCase (e.g., myMethod)
- Constants and Enum Cases names: Use SCREAMING_SNAKE_CASE (e.g., MY_CONSTANT)
- Use strict types everywhere and all the time
- Always assume max level strictness for PHPStan
- Use `composer run lint` to check for any PHPStan errors
- Use appropriate `@var` declarations and `assert()` to appease PHPStan
- Do not modify any values within `phpstan.neon.dist` to fix errors
- Prefer constructor injection to facade usage for source code
- You may use facades within test code for ease of use

2. **Testing Guidelines**:

- All tests will be run in parallel and should be designed with parallelization in mind
- Test should be run with the command `composer run test`
- Follow the Arrange-Act-Assert pattern when writing all tests
- Prefer to keep tests small in scope, testing a specific behavior
- Tests should be written using the Pest framework
- All tests should use the `covers()` within them to allow for code coverage metrics
- All tests should be wrapped within `describe()` blocks, followed by subsequent `it()` blocks like the following:

```php
covers(Calculator::class);

describe(ClassUnderTest::class, function (): void {
    it('ensure correct behavior', function (): void {
        // Arrange
        $sut = new Calculator();

        // Act
        $result = $sut->addNumbers(1, 2);

        // Assert
        expect($result)->toBe(3);
    });
});
```

**Important Notes**:

- Remember to adhere to the specified coding standards and development guidelines
- Laravel best practices throughout your plan and code samples
- Ensure that your response is detailed and well-structured
- Ensure code provides a clear roadmap for developing the Laravel package based on the description and requirements