<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Component\Form\Extension\DataCollector\FormDataCollector;
use Symfony\Component\VarDumper\Cloner\Data;

use function is_array;
use function is_int;
use function is_numeric;
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
        $this->assertGreaterThan(0, count($node), sprintf('Form "%s" not found.', $formSelector));

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
        $this->assertGreaterThan(0, count($node), sprintf('Form "%s" not found.', $formSelector));

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
        $this->assertSame(0, $this->getFormErrorsCount(__FUNCTION__), 'Expecting that the form does not have errors, but there were!');
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
        $errors = $this->getErrorsForField($field);

        if ($errors === []) {
            $this->fail("No form error message for field '{$field}'.");
        }

        if ($message !== null) {
            $this->assertStringContainsString(
                $message,
                implode("\n", $errors),
                sprintf("There is an error message for the field '%s', but it does not match the expected message.", $field)
            );
        }
    }

    /**
     * Verifies that multiple fields on a form have errors.
     *
     * Use a list of field names when you only need to assert that each field
     * has at least one validation error:
     *
     * ```php
     * <?php
     * $I->seeFormErrorMessages(['telephone', 'address']);
     * ```
     *
     * Use an associative array to also verify the error text for one or more
     * fields. The expected message is matched as a substring, so partial
     * fragments are allowed:
     *
     * ```php
     * <?php
     * $I->seeFormErrorMessages([
     *     'address'   => 'The address is too long',
     *     'telephone' => 'too short',
     * ]);
     * ```
     *
     * You can mix both styles in the same call. If a field maps to `null`,
     * only the existence of an error is checked for that field:
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
            is_int($field) ? $this->seeFormErrorMessage((string) $msg) : $this->seeFormErrorMessage($field, $msg);
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
        $this->assertGreaterThan(0, $this->getFormErrorsCount(__FUNCTION__), 'Expecting that the form has errors, but there were none!');
    }

    protected function grabFormCollector(string $function): FormDataCollector
    {
        return $this->grabCollector(DataCollectorName::FORM, $function);
    }

    private function getFormErrorsCount(string $function): int
    {
        $collector = $this->grabFormCollector($function);
        $rawData = $this->getRawCollectorData($collector);

        return isset($rawData['nb_errors']) && is_numeric($rawData['nb_errors']) ? (int) $rawData['nb_errors'] : 0;
    }

    /**
     * @return list<string>
     */
    private function getErrorsForField(string $field): array
    {
        $collector = $this->grabFormCollector('seeFormErrorMessage');
        $formsData = $this->getRawCollectorData($collector)['forms'] ?? [];
        if (!is_array($formsData)) {
            return [];
        }

        $errorsForField = [];
        $fieldFound = false;

        foreach ($formsData as $form) {
            if (!is_array($form) || !isset($form['children']) || !is_array($form['children'])) {
                continue;
            }

            foreach ($form['children'] as $child) {
                if (!is_array($child) || ($child['name'] ?? null) !== $field) {
                    continue;
                }
                $fieldFound = true;
                if (isset($child['errors']) && is_array($child['errors'])) {
                    foreach ($child['errors'] as $error) {
                        if (is_array($error) && isset($error['message']) && is_string($error['message'])) {
                            $errorsForField[] = $error['message'];
                        }
                    }
                }
            }
        }

        if (!$fieldFound) {
            $this->fail("The field '{$field}' does not exist in the form.");
        }

        return $errorsForField;
    }

    /** @return array<string, mixed> */
    private function getRawCollectorData(FormDataCollector $collector): array
    {
        $data = $collector->getData();
        if ($data instanceof Data) {
            $data = $data->getValue(true);
        }
        /** @var array<string, mixed> */
        return is_array($data) ? $data : [];
    }
}
