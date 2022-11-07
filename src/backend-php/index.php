<?php

function getFilePaths($filename){
	$paths = array();
	$conn = new COM("ADODB.Connection") or die("Cannot start ADO");
	$conn->Open("Provider=Search.CollatorDSO;Extended Properties='Application=Windows';");
	$recordset = new COM("ADODB.Recordset");
	$recordset->MaxRecords = 150;
	$recordset->Open("SELECT System.ItemPathDisplay FROM SystemIndex WHERE SCOPE='D:/X_Drive' AND FileName='$filename'", $conn);

	if(!$recordset->EOF){
		$recordset->MoveFirst();
		while(!$recordset->EOF) {
			$path = $recordset->Fields->Item("System.ItemPathDisplay")->value;
			$paths[] = $path;
			$recordset->MoveNext();
		}
	}
	return $paths;
}

function getGroup($projectFilePath, $groupFilePaths){
	foreach ($groupFilePaths as $groupFilePath){
		$groupDir = dirname($groupFilePath);
		if (strstr($projectFilePath, $groupDir)){
			return $groupDir;
		}
	}
	return "ungrouped";
}

function getProjectsByGroup($projectFilePaths, $groupFilePaths){
	$groupedProjects = [];
	foreach ($projectFilePaths as $projectFilePath){
		$group = getGroup($projectFilePath, $groupFilePaths);
		$group = xPath($group);
		$project = getProject($projectFilePath);
		$groupedProjects[$group][] = $project;
	}
	return $groupedProjects;
}

function getProject($projectFilePath){
	$txt = file_get_contents($projectFilePath);
	$txt = str_replace("\r", "", $txt);
	$lines = explode("\n", $txt, 2);
	$title = trim($lines[0], "\n");
	$description = trim($lines[1], "\n");
	return [
		"path" => xPath(dirname($projectFilePath)),
		"title" => $title, 
		"description" => $description,
	];
}

function xPath($dPath){
	$xPath = $dPath;
	$xPath = str_replace("D:\\X_Drive", "X:", $xPath);
	$xPath = str_replace("\\", "/", $xPath);
	return $xPath;
}

$projectFilePaths = getFilePaths("project.txt");
$groupFilePaths = getFilePaths("project-group.txt");
$groupedProjects = getProjectsByGroup($projectFilePaths, $groupFilePaths);

header('Content-Type: application/json');
echo json_encode($groupedProjects, JSON_PRETTY_PRINT);