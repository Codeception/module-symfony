<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Component\Form\Extension\DataCollector\FormDataCollector;
use function array_key_exists;
use function in_array;
use function is_int;
use function sprintf;

trait FormAssertionsTrait
{
    /**
     * Verifies that there are no errors bound to the submitted form.
     *
     * ```php
     * <?php
     * $I->dontSeeFormErrors();
     * ```
     */
    public function dontSeeFormErrors(): void
    {
        $formCollector = $this->grabFormCollector(__FUNCTION__);

        $errors = (int)$formCollector->getData()->offsetGet('nb_errors');

        $this->assertSame(
            0,
            $errors,
            'Expecting that the form does not have errors, but there were!'
        );
    }

    /**
     * Verifies that a form field has an error.
     * You can specify the expected error message as second parameter.
     *
     * ```php
     * <?php
     * $I->seeFormErrorMessage('username');
     * $I->seeFormErrorMessage('username', 'Username is empty');
     * ```
     *
     * @param string $field
     * @param string|null $message
     */
    public function seeFormErrorMessage(string $field, string $message = null): void
    {
        $formCollector = $this->grabFormCollector(__FUNCTION__);

        if (!$forms = $formCollector->getData()->getValue(true)['forms']) {
            $this->fail('There are no forms on the current page.');
        }

        $fields = [];
        $errors = [];

        foreach ($forms as $form) {
            foreach ($form['children'] as $child) {
                $fieldName = $child['name'];
                $fields[] = $fieldName;

                if (!array_key_exists('errors', $child)) {
                    continue;
                }

                foreach ($child['errors'] as $error) {
                    $errors[$fieldName] = $error['message'];
                }
            }
        }

        if (!in_array($field, $fields)) {
            $this->fail("the field '{$field}' does not exist in the form.");
        }

        if (!array_key_exists($field, $errors)) {
            $this->fail("No form error message for field '{$field}'.");
        }

        if (!$message) {
            return;
        }

        $this->assertStringContainsString(
            $message,
            $errors[$field],
            sprintf(
                "There is an error message for the field '%s', but it does not match the expected message.",
                $field
            )
        );
    }

    /**
     * Verifies that multiple fields on a form have errors.
     *
     * If you only specify the name of the fields, this method will
     * verify that the field contains at least one error of any type:
     *
     * ```php
     * <?php
     * $I->seeFormErrorMessages(['telephone', 'address']);
     * ```
     *
     * If you want to specify the error messages, you can do so
     * by sending an associative array instead, with the key being
     * the name of the field and the error message the value.
     *
     * This method will validate that the expected error message
     * is contained in the actual error message, that is,
     * you can specify either the entire error message or just a part of it:
     *
     * ```php
     * <?php
     * $I->seeFormErrorMessages([
     *     'address'   => 'The address is too long'
     *     'telephone' => 'too short', // the full error message is 'The telephone is too short'
     * ]);
     * ```
     *
     * If you don't want to specify the error message for some fields,
     * you can pass `null` as value instead of the message string,
     * or you can directly omit the value of that field. If that is the case,
     * it will be validated that that field has at least one error of any type:
     *
     * ```php
     * <?php
     * $I->seeFormErrorMessages([
     *     'telephone' => 'too short',
     *     'address'   => null,
     *     'postal code',
     * ]);
     * ```
     *
     * @param string[] $expectedErrors
     */
    public function seeFormErrorMessages(array $expectedErrors): void
    {
        foreach ($expectedErrors as $field => $message) {
            if (is_int($field)) {
                $this->seeFormErrorMessage($message);
            } else {
                $this->seeFormErrorMessage($field, $message);
            }
        }
    }

    /**
     * Verifies that there are one or more errors bound to the submitted form.
     *
     * ```php
     * <?php
     * $I->seeFormHasErrors();
     * ```
     */
    public function seeFormHasErrors(): void
    {
        $formCollector = $this->grabFormCollector(__FUNCTION__);

        $this->assertGreaterThan(
            0,
            $formCollector->getData()->offsetGet('nb_errors'),
            'Expecting that the form has errors, but there were none!'
        );
    }

    protected function grabFormCollector(string $function): FormDataCollector
    {
        return $this->grabCollector('form', $function);
    }
}