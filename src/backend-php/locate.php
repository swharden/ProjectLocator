<?php

/* Returns an array of full paths for all files on disk with the given filename.
 */
function getFilePaths($filename)
{
	$paths = array();
	$conn = new COM("ADODB.Connection") or die("Cannot start ADO");
	$conn->Open("Provider=Search.CollatorDSO;Extended Properties='Application=Windows';");
	$recordset = new COM("ADODB.Recordset");
	$recordset->MaxRecords = 150;
	$recordset->Open("SELECT System.ItemPathDisplay FROM SystemIndex WHERE SCOPE='D:/X_Drive' AND FileName='$filename'", $conn);

	if (!$recordset->EOF) {
		$recordset->MoveFirst();
		while (!$recordset->EOF) {
			$path = $recordset->Fields->Item("System.ItemPathDisplay")->value;
			$paths[] = $path;
			$recordset->MoveNext();
		}
	}
	return $paths;
}

/* Returns the full path to the group text file associated with the given project file path.
 * If no group file exist, returns an empty string.
 */
function getGroupFile($projectFilePath, $groupFilePaths)
{
	foreach ($groupFilePaths as $groupFilePath) {
		$groupDir = dirname($groupFilePath);
		if (strstr($projectFilePath, $groupDir)) {
			return $groupFilePath;
		}
	}
	return "";
}

/* Parse a text file and return path/title/description
 * The path is the FOLDER the file lives in.
 * The first line of the file is the TITLE.
 * Additional lines are the DESCRIPTION.
 */
function getFileContents($projectFilePath)
{
	$txt = file_get_contents($projectFilePath);
	$txt = str_replace("\r", "", $txt);
	$lines = explode("\n", $txt, 2);
	$title = trim($lines[0], "\n");
	$description = trim($lines[1], "\n"); // TODO: support markdown
	$interesting = getUsefulFilePaths(dirname($projectFilePath));
	return [
		"path" => xPath(dirname($projectFilePath)),
		"title" => $title,
		"description" => $description,
		"interesting" => $interesting
	];
}

/* Convert a local server path into an X-drive path */
function xPath($dPath)
{
	$xPath = $dPath;
	$xPath = str_replace("D:\\X_Drive", "X:", $xPath);
	$xPath = str_replace("\\", "/", $xPath);
	return $xPath;
}

/* Return a nested object containing groups of projects. */
function getProjectsByGroup($projectFilePaths, $groupFilePaths)
{
	$groupedProjects = [];
	foreach ($projectFilePaths as $projectFilePath) {
		$groupFilePath = getGroupFile($projectFilePath, $groupFilePaths);
		$groupName = "GROUP-" . MD5($groupFilePath);
		$project = getFileContents($projectFilePath);
		if (!in_array($groupName, $groupedProjects)) {
			if (file_exists($groupFilePath)) {
				$groupDetails = getFileContents($groupFilePath);
				$groupedProjects[$groupName]["path"] = $groupDetails["path"];
				$groupedProjects[$groupName]["title"] = $groupDetails["title"];
				$groupedProjects[$groupName]["description"] = $groupDetails["description"];
			} else {
				$groupedProjects[$groupName]["path"] = "";
				$groupedProjects[$groupName]["title"] = "ungrouped";
				$groupedProjects[$groupName]["description"] = "projects with no group file";
			}
		}
		$groupedProjects[$groupName]["projects"][] = $project;
	}
	return $groupedProjects;
}

/* Return an array of useful files like PPT, PDF, TXT, OPJU, etc. */
function getUsefulFilePaths($folderPath)
{
	$interestingExtensions = ["pdf", "html", "docx", "opj", "opju", "png", "jpg"];
	$interestingPaths = [];
	foreach (scandir($folderPath) as $filePath) {
		$extension = pathinfo($filePath)['extension'];
		if (in_array($extension, $interestingExtensions))
			$interestingPaths[] .= xPath($folderPath . DIRECTORY_SEPARATOR . $filePath);
	}
	return $interestingPaths;
}

$projectFilePaths = getFilePaths("project.txt");
$groupFilePaths = getFilePaths("project-group.txt");
$groupedProjects = getProjectsByGroup($projectFilePaths, $groupFilePaths);

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
echo json_encode($groupedProjects, JSON_PRETTY_PRINT);
