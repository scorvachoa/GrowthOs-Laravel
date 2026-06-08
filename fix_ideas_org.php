<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$count = \DB::table('ideas')->where('organization_id', '!=', 1)->orWhereNull('organization_id')->update(['organization_id' => 1]);
echo "Updated $count ideas to org_id=1\n";

echo "\nIdeas overview:\n";
foreach (\DB::table('ideas')->select('id', 'title', 'organization_id')->get() as $idea) {
    echo "  #$idea->id $idea->title (org=$idea->organization_id)\n";
}
unlink(__FILE__);
