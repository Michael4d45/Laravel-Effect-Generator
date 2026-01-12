<?php

declare(strict_types=1);

use EffectSchemaGenerator\Discovery\ClassDiscoverer;

beforeEach(function () {
    $this->discoverer = app(ClassDiscoverer::class);
});

it('discovers data classes', function () {
    $classes = $this->discoverer->discoverDataClasses();

    expect($classes)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($classes->count())->toBeGreaterThan(0);
    
    // Should find at least some of our fixture data classes
    $classNames = $classes->toArray();
    expect($classNames)->toContain(\EffectSchemaGenerator\Tests\Fixtures\UserData::class);
    expect($classNames)->toContain(\EffectSchemaGenerator\Tests\Fixtures\AddressData::class);
});

it('discovers enums', function () {
    $enums = $this->discoverer->discoverEnums();

    expect($enums)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($enums->count())->toBeGreaterThan(0);
    
    // Should find our fixture enums
    $enumNames = $enums->toArray();
    expect($enumNames)->toContain(\EffectSchemaGenerator\Tests\Fixtures\Color::class);
    expect($enumNames)->toContain(\EffectSchemaGenerator\Tests\Fixtures\Priority::class);
    expect($enumNames)->toContain(\EffectSchemaGenerator\Tests\Fixtures\TestStatus::class);
});

it('handles non-existent paths gracefully', function () {
    $discoverer = new ClassDiscoverer(['/non/existent/path']);
    
    $classes = $discoverer->discoverDataClasses();
    $enums = $discoverer->discoverEnums();
    
    expect($classes->count())->toBe(0);
    expect($enums->count())->toBe(0);
});

it('handles empty paths', function () {
    $discoverer = new ClassDiscoverer([]);
    
    $classes = $discoverer->discoverDataClasses();
    $enums = $discoverer->discoverEnums();
    
    expect($classes->count())->toBe(0);
    expect($enums->count())->toBe(0);
});

it('handles files without content', function () {
    // Create a temporary directory and empty file
    $tempDir = sys_get_temp_dir() . '/test_discoverer_' . uniqid();
    mkdir($tempDir, 0755, true);
    $tempFile = $tempDir . '/empty_test_file.php';
    touch($tempFile);
    
    $discoverer = new ClassDiscoverer([$tempDir]);
    
    // Should not crash when encountering empty file (line 100)
    $classes = $discoverer->discoverDataClasses();
    $enums = $discoverer->discoverEnums();
    
    // Clean up
    unlink($tempFile);
    rmdir($tempDir);
    
    expect($classes)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($enums)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});

it('handles files without namespace or class', function () {
    // Create a temporary directory and file without proper structure
    $tempDir = sys_get_temp_dir() . '/test_discoverer_' . uniqid();
    mkdir($tempDir, 0755, true);
    $tempFile = $tempDir . '/invalid_test_file.php';
    file_put_contents($tempFile, '<?php echo "test";');
    
    $discoverer = new ClassDiscoverer([$tempDir]);
    
    // Should not crash when encountering invalid file (line 123)
    $classes = $discoverer->discoverDataClasses();
    $enums = $discoverer->discoverEnums();
    
    // Clean up
    unlink($tempFile);
    rmdir($tempDir);
    
    expect($classes)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($enums)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});

it('handles Surveyor exceptions gracefully', function () {
    // This tests the catch block in isDataClass (line 146)
    // Create a file with a class that looks like Data but will cause Surveyor to fail
    $tempDir = sys_get_temp_dir() . '/test_discoverer_' . uniqid();
    mkdir($tempDir, 0755, true);
    $tempFile = $tempDir . '/BadDataClass.php';
    
    // Create a file that will be discovered but Surveyor might fail on
    file_put_contents($tempFile, <<<'PHP'
<?php
namespace Test\Namespace;
class BadDataClass extends \Spatie\LaravelData\Data {
    // This class extends Data but might cause issues
}
PHP
    );
    
    $discoverer = new ClassDiscoverer([$tempDir]);
    
    // Should not crash even if Surveyor has issues - should fallback to is_subclass_of
    $classes = $discoverer->discoverDataClasses();
    
    // Clean up
    unlink($tempFile);
    rmdir($tempDir);
    
    expect($classes)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    // The fallback should still work
});
