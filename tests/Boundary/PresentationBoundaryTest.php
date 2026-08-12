<?php

declare(strict_types=1);

/*
 * The rules specific to wave 9's Filament package. The fleet's shared rules ship
 * with the testbench and are wired into the same suite from vendor/.
 *
 * Tax owns rate determination, the tax arithmetic and the evidence that both
 * happened. It owns no price, no order and no filing. Five siblings are named
 * one by one rather than caught by a pattern, because a pattern quietly stops
 * covering a module somebody renames.
 */

const SIBLING_NAMESPACES = [
    'Liberu\Ecommerce\Pricing\\',
    'Liberu\Ecommerce\Cart\\',
    'Liberu\Ecommerce\Checkout\\',
    'Liberu\Ecommerce\Orders\\',
    'Liberu\Ecommerce\Refunds\\',
    'Liberu\Ecommerce\MultiTenderPayments\\',
];

const SIBLING_PACKAGES = [
    'liberusoftware/ecommerce-pricing',
    'liberusoftware/ecommerce-cart',
    'liberusoftware/ecommerce-checkout',
    'liberusoftware/ecommerce-orders',
    'liberusoftware/ecommerce-refunds',
    'liberusoftware/ecommerce-multi-tender-payments',
];

function packageFile(string $path): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/'.$path);
}

/** @return list<string> */
function packageSourceFiles(): array
{
    $files = [];

    /** @var SplFileInfo $file */
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src')) as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

it('reaches for no sibling module in its source', function (string $namespace) {
    foreach (packageSourceFiles() as $file) {
        expect((string) file_get_contents($file))->not->toContain($namespace);
    }
})->with(SIBLING_NAMESPACES);

it('requires no sibling module in its manifest', function (string $package) {
    $composer = json_decode(packageFile('composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['require'])->not->toHaveKey($package)
        ->and($composer['require-dev'])->not->toHaveKey($package);
})->with(SIBLING_PACKAGES);

it('names every sibling it disclaims, so a rename cannot quietly drop one', function () {
    expect(SIBLING_NAMESPACES)->toHaveCount(6)
        ->and(SIBLING_PACKAGES)->toHaveCount(6);
});

it('owns no schema of its own', function () {
    // A presentation package renders somebody else's tables. A migration here
    // would be a second owner for the same data.
    expect(is_dir(dirname(__DIR__, 2).'/database'))->toBeFalse();
});

it('declares the domain package in exactly the same terms in both manifests', function () {
    $composer = json_decode(packageFile('composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $module = json_decode(packageFile('module.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($module['requires']['packages'])->toBe(['liberusoftware/ecommerce-tax' => $composer['require']['liberusoftware/ecommerce-tax']]);
});

it('tells a host where to find the domain package, which is not on Packagist', function () {
    $composer = json_decode(packageFile('composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['repositories'][0]['url'])->toBe('https://github.com/liberusoftware/module-ecommerce-tax');
});
