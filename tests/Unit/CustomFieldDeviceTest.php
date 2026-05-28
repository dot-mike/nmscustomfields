<?php

namespace DotMike\NmsCustomFields\Tests\Unit;

use DotMike\NmsCustomFields\Models\CustomField;
use DotMike\NmsCustomFields\Models\CustomFieldDevice;
use DotMike\NmsCustomFields\Tests\TestCase;

final class CustomFieldDeviceTest extends TestCase
{
    public function test_mutator_routes_integer_value_to_value_int(): void
    {
        $cf = CustomField::create(['name' => 'mut_int', 'type' => 'integer']);
        $cfd = new CustomFieldDevice();
        $cfd->custom_field_id = $cf->id;
        $cfd->value = '42';

        $this->assertSame(42, $cfd->getAttribute('value_int'));
        $this->assertNull($cfd->getAttribute('value_text'));
    }

    public function test_mutator_routes_text_value_to_value_text(): void
    {
        $cf = CustomField::create(['name' => 'mut_txt', 'type' => 'text']);
        $cfd = new CustomFieldDevice();
        $cfd->custom_field_id = $cf->id;
        $cfd->value = 'hello';

        $this->assertSame('hello', $cfd->getAttribute('value_text'));
        $this->assertNull($cfd->getAttribute('value_int'));
    }

    public function test_mutator_clears_other_column_on_overwrite(): void
    {
        $cf = CustomField::create(['name' => 'mut_swap', 'type' => 'integer']);
        $cfd = new CustomFieldDevice();
        $cfd->custom_field_id = $cf->id;
        $cfd->value = '7';

        // Now imagine the field's type was switched and we re-set: the mutator
        // should null the previously-populated column.
        $cfd->setAttribute('value_text', 'stale');
        $cfd->value = '99';

        $this->assertSame(99, $cfd->getAttribute('value_int'));
        $this->assertNull($cfd->getAttribute('value_text'));
    }

    public function test_mutator_throws_without_custom_field_id(): void
    {
        $cfd = new CustomFieldDevice();
        $this->expectException(\LogicException::class);
        $cfd->value = 'anything';
    }

    public function test_accessor_returns_int_for_integer(): void
    {
        $cf = CustomField::create(['name' => 'acc_int', 'type' => 'integer']);
        $cfd = new CustomFieldDevice();
        $cfd->setAttribute('custom_field_id', $cf->id);
        $cfd->setAttribute('value_int', 42);

        $this->assertSame(42, $cfd->value);
    }

    public function test_accessor_returns_string_for_text(): void
    {
        $cf = CustomField::create(['name' => 'acc_txt', 'type' => 'text']);
        $cfd = new CustomFieldDevice();
        $cfd->setAttribute('custom_field_id', $cf->id);
        $cfd->setAttribute('value_text', 'hello');

        $this->assertSame('hello', $cfd->value);
    }

    public function test_accessor_returns_null_when_both_columns_null(): void
    {
        $cfd = new CustomFieldDevice();
        $this->assertNull($cfd->value);
    }

    /** Regression guard for the false-zero bug — integer 0 must surface as 0, not null. */
    public function test_accessor_returns_zero_for_integer_zero(): void
    {
        $cf = CustomField::create(['name' => 'acc_zero', 'type' => 'integer']);
        $cfd = new CustomFieldDevice();
        $cfd->setAttribute('custom_field_id', $cf->id);
        $cfd->setAttribute('value_int', 0);

        $this->assertSame(0, $cfd->value);
    }

    public function test_columns_for_integer_casts_and_clears_text(): void
    {
        $this->assertSame(
            ['value_int' => 42, 'value_text' => null],
            CustomFieldDevice::columnsFor('integer', '42')
        );
    }

    public function test_columns_for_text_casts_and_clears_int(): void
    {
        $this->assertSame(
            ['value_text' => 'hello', 'value_int' => null],
            CustomFieldDevice::columnsFor('text', 'hello')
        );
    }

    public function test_columns_for_null_preserves_null_on_typed_column(): void
    {
        $this->assertSame(
            ['value_int' => null, 'value_text' => null],
            CustomFieldDevice::columnsFor('integer', null)
        );
        $this->assertSame(
            ['value_text' => null, 'value_int' => null],
            CustomFieldDevice::columnsFor('text', null)
        );
    }

    public function test_columns_for_unknown_type_falls_back_to_text(): void
    {
        $this->assertSame(
            ['value_text' => 'x', 'value_int' => null],
            CustomFieldDevice::columnsFor('mystery', 'x')
        );
    }

    public function test_value_rule_integer(): void
    {
        $cf = new CustomField(['name' => 'rule_int', 'type' => 'integer']);
        $this->assertSame('required|integer', $cf->valueRule());
    }

    public function test_value_rule_text(): void
    {
        $cf = new CustomField(['name' => 'rule_txt', 'type' => 'text']);
        $this->assertSame('required', $cf->valueRule());
    }
}
