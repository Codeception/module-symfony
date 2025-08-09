<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Component\Form\Extension\DataCollector\FormDataCollector;
use Symfony\Component\VarDumper\Cloner\Data;

use function is_array;
use function is_int;
use function is_string;
use function sprintf;

trait FormAssertionsTrait
{
    /**
     * Asserts that value of the field of the first form matching the given selector does equal the expected value.
     *
     * ```php
     * <?php
     * $I->assertFormValue('#loginForm', 'username', 'john_doe');
     * ```
     */
    public function assertFormValue(string $formSelector, string $fieldName, string $value, string $message = ''): void
    {
        $node = $this->getClient()->getCrawler()->filter($formSelector);
        $this->assertNotEmpty($node, sprintf('Form "%s" not found.', $formSelector));

        $values = $node->form()->getValues();
        $this->assertArrayHasKey(
            $fieldName,
            $values,
            $message ?: sprintf('Field "%s" not found in form "%s".', $fieldName, $formSelector)
        );
        $this->assertSame($value, $values[$fieldName]);
    }

    /**
     * Asserts that the field of the first form matching the given selector does not have a value.
     *
     * ```php
     * <?php
     * $I->assertNoFormValue('#registrationForm', 'middle_name');
     * ```
     */
    public function assertNoFormValue(string $formSelector, string $fieldName, string $message = ''): void
    {
        $node = $this->getClient()->getCrawler()->filter($formSelector);
        $this->assertNotEmpty($node, sprintf('Form "%s" not found.', $formSelector));

        $values = $node->form()->getValues();
        $this->assertArrayNotHasKey(
            $fieldName,
            $values,
            $message ?: sprintf('Field "%s" has a value in form "%s".', $fieldName, $formSelector)
        );
    }

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
        $errors        = $this->extractFormCollectorScalar($formCollector, 'nb_errors');

        $this->assertSame(0, $errors, 'Expecting that the form does not have errors, but there were!');
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
     */
    public function seeFormErrorMessage(string $field, ?string $message = null): void
    {
        $formCollector = $this->grabFormCollector(__FUNCTION__);
        $rawData       = $this->getRawCollectorData($formCollector);
        $formsData     = array_values(is_array($rawData['forms'] ?? null) ? $rawData['forms'] : []);

        $fieldExists    = false;
        $errorsForField = [];

        foreach ($formsData as $form) {
            if (!is_array($form)) {
                continue;
            }
            $children = is_array($form['children'] ?? null) ? $form['children'] : [];
            foreach ($children as $child) {
                if (!is_array($child) || ($child['name'] ?? null) !== $field) {
                    continue;
                }

                $fieldExists = true;

                $errs = is_array($child['errors'] ?? null) ? $child['errors'] : [];
                foreach ($errs as $error) {
                    if (is_array($error) && is_string($error['message'] ?? null)) {
                        $errorsForField[] = $error['message'];
                    }
                }
            }
        }

        if (!$fieldExists) {
            $this->fail("The field '{$field}' does not exist in the form.");
        }

        if ($errorsForField === []) {
            $this->fail("No form error message for field '{$field}'.");
        }

        if ($message === null) {
            return;
        }

        $this->assertStringContainsString(
            $message,
            implode("\n", $errorsForField),
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
     * This method will validate that the expected error message
     * is contained in the actual error message, that is,
     * you can specify either the entire error message or just a part of it:
     *
     * ```php
     * <?php
     * $I->seeFormErrorMessages([
     *     'address'   => 'The address is too long',
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
     * @param array<int|string, string|null> $expectedErrors
     */
    public function seeFormErrorMessages(array $expectedErrors): void
    {
        foreach ($expectedErrors as $field => $msg) {
            if (is_int($field)) {
                $this->seeFormErrorMessage((string) $msg);
            } else {
                $this->seeFormErrorMessage($field, $msg);
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
        $errors        = $this->extractFormCollectorScalar($formCollector, 'nb_errors');

        $this->assertGreaterThan(0, $errors, 'Expecting that the form has errors, but there were none!');
    }

    private function extractFormCollectorScalar(FormDataCollector $collector, string $key): int
    {
        $rawData  = $this->getRawCollectorData($collector);
        $valueRaw = $rawData[$key] ?? null;

        return is_numeric($valueRaw) ? (int) $valueRaw : 0;
    }

    /** @return array<string, mixed> */
    private function getRawCollectorData(FormDataCollector $collector): array
    {
        $data = $collector->getData();

        if ($data instanceof Data) {
            $data = $data->getValue(true);
        }

        /** @var array<string, mixed> $result */
        $result = is_array($data) ? $data : [];
        return $result;
    }

    protected function grabFormCollector(string $function): FormDataCollector
    {
        return $this->grabCollector(DataCollectorName::FORM, $function);
    }
}
