<?php

namespace Tests\Unit\Items;

use PHPUnit\Framework\TestCase;

/**
 * The size guide is a table of measures: its editor must make tables and images. Tiptap's table
 * extension is heavy, so it loads only with the editor (behind defineAsyncComponent) and only for
 * the fields that ask for it.
 */
class SizeGuideEditorTest extends TestCase
{
    public function test_the_category_size_guide_editor_makes_tables_and_images(): void
    {
        $fields = $this->source('Modules/Items/resources/js/Pages/Category/CategoryFields.vue');
        $this->assertMatchesRegularExpression('/<RichEditor\s[^>]*v-model="form\.size_guide"[^>]*\btables\b/s', $fields);

        $editor = $this->source('resources/js/Components/form/RichEditorField.vue');
        foreach (['@tiptap/extension-table', '@tiptap/extension-image'] as $package) {
            $this->assertStringContainsString("import('{$package}')", $editor, "{$package} loads only when a field asks for tables");
        }
        foreach (['insertTable', 'addRowAfter', 'deleteRow', 'addColumnAfter', 'deleteColumn'] as $command) {
            $this->assertStringContainsString($command, $editor);
        }

        $wrapper = $this->source('resources/js/Components/form/RichEditor.vue');
        $this->assertStringContainsString('defineAsyncComponent', $wrapper);
    }

    public function test_the_table_extensions_match_the_installed_tiptap(): void
    {
        $package = json_decode($this->source('package.json'), true, flags: JSON_THROW_ON_ERROR);
        $core = json_decode($this->source('node_modules/@tiptap/core/package.json'), true, flags: JSON_THROW_ON_ERROR)['version'];

        foreach (['@tiptap/extension-table', '@tiptap/extension-image'] as $name) {
            $this->assertArrayHasKey($name, $package['dependencies']);
            $this->assertSame($core, json_decode($this->source("node_modules/{$name}/package.json"), true, flags: JSON_THROW_ON_ERROR)['version'], $name);
        }
    }

    private function source(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 3).'/'.$path);
    }
}
