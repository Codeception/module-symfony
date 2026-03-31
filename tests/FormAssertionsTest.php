<?php

declare(strict_types=1);

namespace Tests;

use Codeception\Module\Symfony\FormAssertionsTrait;
use Tests\Support\CodeceptTestCase;

final class FormAssertionsTest extends CodeceptTestCase
{
    use FormAssertionsTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->request('GET', '/sample');
    }

    public function testAssertFormValue(): void
    {
        $this->assertFormValue('#testForm', 'field1', 'value1');
    }

    public function testAssertNoFormValue(): void
    {
        $this->assertNoFormValue('#testForm', 'missing_field');
    }

    public function testDontSeeFormErrors(): void
    {
        $this->client->request('POST', '/form', ['registration_form' => ['email' => 'john@example.com', 'password' => 'top-secret']]);
        $this->dontSeeFormErrors();
    }

    public function testSeeFormErrorMessage(): void
    {
        $this->client->request('POST', '/form', ['registration_form' => ['email' => 'not-an-email', 'password' => '']]);
        $this->seeFormErrorMessage('email', 'valid email address');
    }

    public function testSeeFormErrorMessages(): void
    {
        $this->client->request('POST', '/form', ['registration_form' => ['email' => 'not-an-email', 'password' => '']]);
        $this->seeFormErrorMessages(['email' => 'valid email address', 'password' => 'not be blank']);
    }

    public function testSeeFormHasErrors(): void
    {
        $this->client->request('POST', '/form', ['registration_form' => ['email' => 'not-an-email', 'password' => '']]);
        $this->seeFormHasErrors();
    }
}
