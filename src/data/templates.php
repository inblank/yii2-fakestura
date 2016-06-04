<?php
return [
    'person' => '{name}{last}',

    'fullname' => '{name}{middle}{last}',

    'company' => '{?prefix} "{?adjective}{noun}"',

    'team'=>'"{?adjective}{noun}"',

    'address'=>'{postcode}{country}{city}{street}{number}',

    'login'=>'{login}',

    'email'=>'!{login}{?##}@{@domain|example.com}',
];
