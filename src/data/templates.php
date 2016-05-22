<?php
return [
    'person' => '{name}{last}',

    'fullname' => '{name}{middle}{last}',

    'company' => '{?prefix} "{?adjective}{noun}"',

    'team'=>'"{?adjective}{noun}"',

    'address'=>'{city}{street} {##}',

    'login'=>'{login}',

    'email'=>'!{login}{?##}@{@domain|example.com}',
];
