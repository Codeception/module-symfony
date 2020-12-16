# How to Contribute

First of all: Contributions are very welcome!

Does your change require a test?

## Yes, My Change Requires a Test

So you're going to add or modify functionality? Hey, you rock!

You can use our prepared [Codeception/symfony-module-test](https://github.com/Codeception/symfony-module-tests). It is a minimal (but complete) Symfony project, ready to run tests.

1. On https://github.com/Codeception/symfony-module-tests, click on the "Fork" button. Then, in your terminal, do:
   ```bash
   git clone https://github.com/YourUserName/symfony-module-tests.git
   cd symfony-module-tests
   git remote add upstream https://github.com/Codeception/symfony-module-tests.git
   git pull upstream master
   git rebase upstream/master
   git checkout -b new_feature
   ```

2. Continue with step 2+3 at https://github.com/Codeception/symfony-module-tests/blob/main/README.md (i.e. composer + Doctrine fixtures)

3. Edit the module's source code in `vendor/codeception/module-symfony/src/Codeception/Module/Symfony.php` of this project. If you want, you can already write the tests (see step 8).

4. On https://github.com/Codeception/module-symfony, click on the "Fork" button. Then, in your terminal, go to another directory, then:
   ```bash
   git clone https://github.com/YourUserName/module-symfony.git
   cd module-symfony
   git remote add upstream https://github.com/Codeception/module-symfony.git
   git pull upstream master
   git rebase upstream/master
   git checkout -b new_feature
   ```

5. Copy your changed code parts from the test project's `Symfony.php` to this fork's `src/Codeception/Module/Symfony.php`

6. Commit:
   ```bash
   git add --all
   git commit --message="Briefly explain what your change is about"
   git push --set-upstream origin new_feature
   ```

7. In the CLI output, click on the link to https://github.com/YourUserName/module-symfony/pull/new/new_feature to create a Pull Request through GitHub.com.

Now wait for feedback on your Pull Request.  
If all is fine, then ...

### ... Write the Test

8. In the test project (`symfony-module-tests`), create a test with the same name as your new function in `tests/Functional/SymfonyModuleCest.php`, following alphabetical order.  
   Hint: Run this to rebuild Codeception's "Actor" classes (see [Console Commands](https://codeception.com/docs/reference/Commands#Build)) to get auto-completion in your IDE:
   ```bash
   vendor/bin/codecept clean
   vendor/bin/codecept build
   ```

9. Run the tests with `vendor/bin/codecept run Functional`

10. Commit:
    ```bash
    git add --all
    git commit --message="Add a link to the module's Pull Request you created above"
    git push --set-upstream origin new_feature
    ```

11. In the CLI output, click on the link to https://github.com/YourUserName/symfony-module-test/pull/new/new_feature to create a Pull Request through GitHub.com.


## No, My Change Does Not Require a Test

So you're going to improve documentation, or just do a really minor code change? Hey, you rock too!

* Either just edit https://github.com/Codeception/module-symfony/blob/master/src/Codeception/Module/Symfony.php on GitHub's website.
* Or follow steps 4 through 7 from above to do it on your local machine.
