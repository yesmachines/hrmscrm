<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$t = App\Models\DocumentTemplate::find(3);
if ($t) {
    $code = $t->template_code;
    // Replace the "To" block variables
    $code = str_replace("<p style=\"margin: 0; \">{{bank_name}}</p>\n <p style=\"margin: 0;\">{{to_address}}</p>", "<p style=\"margin: 0; \">{{to_address}}</p>", $code);
    
    // Replace the specific text with {{purpose}}
    $code = str_replace("request of the employee for the application of Personal Loan at <strong>{{bank_name}}</strong>", "<strong>{{purpose}}</strong>", $code);
    
    $t->template_code = $code;
    $t->save();
}

echo "Done fixing template 3";
