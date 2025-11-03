# Abstract Test Class Warning

## The Warning

When running PHPUnit, you may see:

```
There was 1 PHPUnit test runner warning:

1) Class Tests\Integration\Infrastructure\Migrations\Doctrine\AbstractDoctrineMigrationTest declared in ... is abstract
```

## Why This Happens

PHPUnit scans for classes that extend `TestCase` and tries to execute them as tests. When it finds an **abstract** class, it cannot execute it (because abstract classes can't be instantiated), so it shows this informational warning.

## Is This a Problem?

**No!** This warning is **harmless and expected**.

- ✅ Abstract test base classes are a **common pattern** for sharing test setup
- ✅ PHPUnit correctly skips abstract classes (they're not executed)
- ✅ Only **concrete** test classes that extend the abstract class actually run
- ✅ All your actual tests (e.g., `Version20251103000000Test`) run normally

## Our Use Case

`AbstractDoctrineMigrationTest` is an abstract base class that:
- Provides common database setup
- Provides helper methods for table/column assertions
- Must be extended by concrete test classes (e.g., `Version20251103000000Test`)

This is a **best practice** for avoiding code duplication across test files.

## Can We Suppress It?

**Not easily** - PHPUnit doesn't have a built-in option to suppress this specific warning for abstract test classes.

**Options:**
1. ✅ **Ignore it** (recommended) - It's harmless and informational
2. Move abstract class outside test directory (breaks test discovery)
3. Use a different pattern (loses benefits of base class)

## Conclusion

**This warning is safe to ignore.** It's PHPUnit's way of saying "I found an abstract test class - I'll skip it (as expected) and only run the concrete classes that extend it."

