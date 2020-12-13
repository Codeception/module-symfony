# How to Contribute

If you want to add or modify the documentation of the functions of this module,
the editor of Github.com may be enough for that task.

If instead you plan to edit or add some new function to this module, hey, you rock!

To do it, make sure you have the test project for this module installed [Codeception/symfony-module-test](https://github.com/Codeception/symfony-module-tests):

After having installed it and confirming that all tests pass, you can start editing the source code in:
```
vendor/codeception/module-symfony/src/Codeception/Module/Symfony.php
```
If you are going to add a new function or modify in some way the parameters of an existing function, make sure to execute:

```bash
vendor/bin/codecept clean
vendor/bin/codecept build
```

This way your new function will be available in your functional tests and your IDE should be able to autocomplete it.

Keep in mind that any new function needs a corresponding test.

If that's your case, create a test with the same name as your new function inside:

```
tests/Functional/SymfonyModuleCest.php
```

following alphabetical order, and verify that it works correctly with various test data. All good? Great!

You can now send your contribution to the project, for that you will only have to fork the project on GitHub,
clone it and create a branch:
```bash
git clone https://github.com/YourUserName/module-symfony.git
git branch <name_of_your_new_branch>
```

And paste your changes. Then, just do:

```bash
git commit
git push --set-upstream origin <name_of_your_new_branch>
```
Finally go back to GitHub and create a Pull Request from `the branch of your fork` to the `master` of this project.



