<?php

if(SUPER_USER !== '1'){
    die("You're not allowed to view this page!");
}

echo '<pre>';

$recordIdFieldName = $module->getRecordIdField();
$addressFieldName = $module->getProjectSetting('address');
$fields = [
    $recordIdFieldName,
    $addressFieldName
];

$censuses = $module->getSubSettings('censuses');
foreach($censuses as $census){
    foreach($census['mappings'] as $mapping){
        $fields[] = $mapping['fields'];
    }
}

$records = json_decode(REDCap::getData([
    'return_format' => 'json',
    'fields' => $fields
]), true);

$dataToSave = [];
foreach($censuses as $census){
    foreach($records as $record){
        $recordId = $record[$recordIdFieldName];
        $module->log('checking record ' . $recordId);
        $address = $record[$addressFieldName];
        $address = str_replace(' ', '+', $address);
        $_POST['get'] = "address=$address&benchmark=Public_AR_Current&vintage=Census{$census['year']}_Current&format=json";

        ob_start();
        require __DIR__ . '/getAddress.php';
        $response = json_decode(ob_get_clean(), true);
        $lookupTable = $response['result']['addressMatches'][0]['geographies']['Census Blocks'][0];

        if(empty($lookupTable)){
            $message = "Error looking up address for record $recordId";
            $module->log($message);
            echo "$message\n";
            continue;
        }

        foreach($census['mappings'] as $mapping){
            $key = $mapping['keys'];
            $field = $mapping['fields'];

            $expected = $lookupTable[$key];
            $actual = $record[$field];

            if($expected != $actual){
                $message = "Record $recordId - Field $field - Expected '$expected', but found '$actual'";
                $module->log($message);
                echo "$message\n";

                if(isset($_GET['save'])){
                    $dataToSave[$recordId][$recordIdFieldName] = $recordId;
                    $dataToSave[$recordId][$field] = $expected;
                }
            }
        }
    }
}

$dataToSave = array_values($dataToSave);

if(!empty($dataToSave)){
    $result = REDCap::saveData([
        'dataFormat' => 'json',
        'data' => json_encode($dataToSave)
    ]);

    var_dump($result);
}

echo '</pre>';
