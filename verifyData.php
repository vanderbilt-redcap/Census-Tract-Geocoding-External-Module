<?php

if(SUPER_USER !== '1'){
    die("You're not allowed to view this page!");
}

$log = function ($message){
    global $module;

    $module->log($message);
    echo "$message\n";
};

echo '<pre>';

$recordIdFieldName = $module->getRecordIdField();
$addressFieldName = $module->getProjectSetting('address');
$latitudeFieldName = $module->getProjectSetting('latitude');
$longitudeFieldName = $module->getProjectSetting('longitude');
$fields = [
    $recordIdFieldName,
    $addressFieldName,
    $latitudeFieldName,
    $longitudeFieldName,
];

$censuses = $module->getSubSettings('censuses');
foreach($censuses as $census){
    foreach($census['mappings'] as $mapping){
        $fields[] = $mapping['fields'];
    }
}

$recordIds = array_column(json_decode(REDCap::getData([
    'return_format' => 'json',
    'fields' => 'stateid',
    'filterLogic' => '[geostatus] != "U" and [streetno] != ""'
]), true), 'stateid');

$id = $_GET['id'] ?? null;
if($id === null){
    $batchSize = (int) $_GET['batch-size'];
    $batch = (int) $_GET['batch'];
    if($batchSize === 0){
        die('You must specify the "batch-size" parameter!');
    }
}
else{
    $batchSize = 1;
    $batch = array_search($id, $recordIds);
    if($batch === false){
        die('Record ID not found');
    }
}

$batches = array_chunk($recordIds, $batchSize);
$recordIds = $batches[$batch] ?? null;
if(!is_array($recordIds)){
    die('Batch not found');
}

$records = json_decode(REDCap::getData([
    'return_format' => 'json',
    'fields' => $fields,
    'records' => $recordIds
]), true);

$dataToSave = [];
foreach($records as $record){
    $recordId = $record[$recordIdFieldName];

    $log('checking record ' . $recordId);

    // $usingAddress = true;
    // $address = $record[$addressFieldName];
    // $address = str_replace(' ', '+', $address);

    $usingAddress = false;
    $latitude = $record[$latitudeFieldName];
    $longitude = $record[$longitudeFieldName];
    if(empty($latitude) || empty($longitude)){
        $log("Record $recordId - GPS coordinates missing");
        continue;
    }

    foreach($censuses as $census){    
        ob_start();
        if($usingAddress){
            $_POST['get'] = "address=$address&" . $module->getSharedArgs($census['year']);
            require __DIR__ . '/getAddress.php';
        }
        else{
            $_POST['get'] = "x=$longitude&y=$latitude&" . $module->getSharedArgs($census['year']);
            require __DIR__ . '/getCoordinates.php';
        }
        $response = json_decode(ob_get_clean(), true);
        
        $lookupTable = $response['result'];
        if($usingAddress){
            $lookupTable = $lookupTable['addressMatches'][0];
        }
        $lookupTable = $lookupTable['geographies']['Census Blocks'][0] ?? [];

        foreach($census['mappings'] as $mapping){
            $key = $mapping['keys'];
            $field = $mapping['fields'];

            $expected = $lookupTable[$key] ?? null;
            $actual = $record[$field];

            if($expected != $actual){
                $log("Record $recordId - Field $field - Expected '$expected', but found '$actual'");

                if(isset($_GET['save']) && $expected !== null){
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
