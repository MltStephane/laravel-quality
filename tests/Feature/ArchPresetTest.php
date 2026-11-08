<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use MltStephane\LaravelQuality\Pest\ArchPreset;

beforeEach(function (): void {
    $this->projectPath = sys_get_temp_dir().'/laravel-quality-'.uniqid();
    $this->files = new Filesystem;
    $this->files->ensureDirectoryExists($this->projectPath.'/app/Actions/Users');
    $this->files->ensureDirectoryExists($this->projectPath.'/tests/Feature/Actions');
});

afterEach(function (): void {
    $this->files->deleteDirectory($this->projectPath);
});

it('lists the actions without a test anywhere under tests', function (): void {
    $this->files->put($this->projectPath.'/app/Actions/CreateInvoice.php', '<?php');
    $this->files->put($this->projectPath.'/app/Actions/Users/CreateUser.php', '<?php');
    $this->files->put($this->projectPath.'/app/Actions/Users/DeleteUser.php', '<?php');
    $this->files->put($this->projectPath.'/tests/Feature/Actions/CreateUserTest.php', '<?php');

    expect(ArchPreset::actionsWithoutTest([$this->projectPath.'/app/Actions'], $this->projectPath.'/tests'))
        ->toBe(['CreateInvoice', 'DeleteUser']);
});

it('passes when the project has no actions', function (): void {
    expect(ArchPreset::actionsWithoutTest([$this->projectPath.'/app/Missing'], $this->projectPath.'/tests'))->toBe([]);
});
