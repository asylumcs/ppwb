<?php
require_once("base.inc");

$errors = array(); // place to save error messages
$work = "t"; // a working folder for project data
$upid = uniqid('r'); // unique project id
$extensions = array( // allowed file extensions
    "zip"
);
$target_name = "";
$gtarget_name = "";

// create a unique workbench project folder in t
mkdir($work . "/" . $upid, 0755);


// ----- process the main project file ---------------------------------

if (isset($_FILES['userfile']) && $_FILES['userfile']['name'] != "") {

    // get the information about the file
    $file_name = $_FILES['userfile']['name'];
    $file_size = $_FILES['userfile']['size'];
    $file_tmp = $_FILES['userfile']['tmp_name'];
    $file_type = $_FILES['userfile']['type'];
    $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
    move_uploaded_file($file_tmp, "/tmp/" . $upid);

    // begin a series of validation tests

    // does it pass the anti-virus tests?
    $av_test_result = array();
    $av_retval = 0;
    $cmd = "/usr/bin/clamdscan " . escapeshellcmd("/tmp/" . $upid);
    exec($cmd, $av_test_result, $av_retval);
    if ($av_retval == 1) {
        $errors[] = "input file rejected by clamdscan";
        // destroy uploaded file
        unlink( escapeshellcmd("/tmp/" . $upid) );
    }

    // was a file uploaded?
    if ($_FILES['userfile']['size'] == 0) {
        $errors[] = 'no file was uploaded';
    }

    // do they claim it's an allowed type?
    if (in_array($file_ext, $extensions) === false) {
        $errors[] = "please upload a .zip file";
    }

    // is it small enough?
    if ($file_size > 40000000) {
        $errors[] = "file size must be less than 40 MB";
    }

    // if any errors, report and terminate
    if (empty($errors) == false) {
        echo "<pre>";
        echo "process terminated during processing uploaded file:<br/>";
        foreach ($errors as $key => $value) {
            echo $value . "<br/>";
        }
        echo "</pre>";
        exit(1);
    }

    // move the uploaded file to the project folder
    $target_name = $work . "/" . $upid . "/" . $file_name;
    rename("/tmp/" . $upid, $target_name);
}
  
// ----- no errors. proceed ----------------------------------------

log_tool_access("pphtml", $upid);

// ----- burst the zip file --------------------------------------------

$zipArchive = new ZipArchive();
$result = $zipArchive->open($target_name);
if ($result === TRUE) {
    $zipArchive->extractTo($work . "/" . $upid);
    $zipArchive->close();
}
else {
    print_r("unable to unzip uploaded file");
    exit(1);
}

// find the name of the user's HTML file. only one allowed

$fileList1 = glob($work . "/" . $upid . '/*.htm');
$fileList2 = glob($work . "/" . $upid . '/*.html');
$user_htmlfile = "";
if (count($fileList1) == 1) {
    $user_htmlfile = $fileList1[0];
}
if (count($fileList2) == 1) {
    $user_htmlfile = $fileList2[0];
}
if ($user_htmlfile == "") {
    echo "could not determine HTML source file name";
    exit(1);       
}

// see if user has ticked the "verbose" box
$verbose = "";
if(isset($_POST['ver']) && $_POST['ver'] == 'Yes') {
    $verbose = " -v ";
}

// ----- run the pphtml command ----------------------------------------

// build the command
// $scommand = './pphtml -i ' . $target_name . ' -o ' . $work . "/" . $upid; // orthogonal
// $scommand = './bin/pphtml -i ' . $user_htmlfile . ' -o ' . $work . "/" . $upid . "/report.html";
$scommand = 'python3 ./bin/pphtml.py ' . $verbose . ' -i "' . $user_htmlfile . '" -o ' . $work . "/" . $upid . "/report.html";

$command = escapeshellcmd($scommand) . " 2>&1";

// echo $command;
file_put_contents("${work}/${upid}/command.txt", $command);

// and finally, run pphtml
$output = shell_exec($command);

// ----- display results -------------------------------------------

output_header("pphtml Results");

$reportok = false;

echo "<p>";
if (file_exists("${work}/${upid}/report.html")) {
   echo "results available: <a href='${work}/${upid}/report.html'>here</a>.<br/>";
   $reportok = true;
}
if ($reportok) {
    echo "Left click to view. Right click to download.</p>";
} else {
    echo "<p>Whoops! Something went wrong and no output was generated.
    The error message was<br/><br/>
    <tt>${output}</tt></p>
    </p>For more assistance, please email rfrank@rfrank.net and include this project name: ${upid}</p>";
}

