<?php
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");

error_reporting(E_ALL);

ini_set("display_errors", 1);
require 'Slim/Slim.php';
//if($_POST)
//{
//    echo "asdfasdf";
//    exit;
//}
\Slim\Slim::registerAutoloader();
$app = new \Slim\Slim();

$app->get('/users', 'getUsers');
$app->get('/users/:id', 'getUser');
$app->get('/users/search/:query', 'findByName');
$app->post('/users', 'addUser');
$app->put('/users/:id', 'updateUser');
$app->delete('/users/:id', 'deleteUser');

//BYG REST API implementation
$app->get('/sports-grounds', 'getAllSportsAndLocations');
$app->get('/sports-grounds/search', 'searchSportsGrounds');
$app->get('/sports-grounds/:groundId', 'getSportsGroundSlots');
$app->post('/sports-grounds/:groundId/slots/:slotId', 'createBooking');
$app->post('/bookings/:paymentId', 'confirmPayment');
$app->put('/bookings/:bookingId/slots', 'addSlotToBooking');
$app->put('/bookings/:bookingId/slots/:slotId', 'cancelSlotFromBooking');
$app->put('/bookings/:bookingId', 'confirmBooking');
$app->delete('/bookings/:bookingId/slots/:slotId', 'removeSlotFromBooking');

$app->run();

function getAllSportsAndLocations()
{
    $sql_query = "select `SportsGroundID`, `Sport`, `City` from X_SportsGround";
    try {
        $dbCon = getBygConnection();
        $stmt = $dbCon->query($sql_query);
        $sportsGrounds = $stmt->fetchAll(PDO::FETCH_OBJ);
        $stmt->closeCursor();
        $dbCon = null;
        echo json_encode($sportsGrounds);
    } catch (PDOException $e) {
        echo '{"error":{"text":' . $e->getMessage() . '}}';
    }

}

function confirmPayment($paymentId)
{
    global $app;
    $paymentDetail = retrievePaymentDetails($paymentId);

    $request = new stdClass();
    if ($paymentDetail->success) {
        $request->paymentStatus = "SUCCESS";
    } else {
        $request->paymentStatus = "FAILURE";
    }

    $request->invoiceId = $paymentDetail->payment->custom_fields->Field_42719->value;
    $request->userId = $paymentDetail->payment->custom_fields->Field_44766->value;
    $request->paymentId = $paymentDetail->payment->payment_id;
    //echo json_encode($request);
    $paymentId = createPayment($request);
    //send SMS and Email (invoice id)
    $invoiceDetails = retrieveInvoiceDetail( $request->invoiceId );
    $invoiceDetails->invoiceNumber= $request->invoiceId ;
    sendSMS($invoiceDetails);
    sendEmail($invoiceDetails);

    $app->response()->header('Content-Type', 'application/json');
    $app->response()->header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
    echo json_encode($paymentId);

}

function sendSMS($invoiceDetail){

    $xml_data = '<?xml version="1.0"?><smslist><sms><user>ground</user><password>123456</password>
<message>BookYourGround BookingID '
        .$invoiceDetail->invoiceNumber . '. Time '
        .$invoiceDetail->playDate .' for '
        .$invoiceDetail->groundSport . ' on '
        .$invoiceDetail->playDate . ' at '
        .$invoiceDetail->groundName .'.Balance to be paid:'
        .$invoiceDetail->bookingSummary->bookingAmt . '+Tax Support +91-8095 887000</message>
<mobiles>' . $invoiceDetail->userDetail->phoneNumber . '</mobiles>
<senderid>BKGRND</senderid>
</sms></smslist>';

    echo $xml_data;
    $URL = "http://sms.jootoor.com/sendsms.jsp?";

    $ch = curl_init($URL);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/xml'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, "$xml_data");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($ch);
    curl_close($ch);
}
function sendEmail($invoiceDetail){

    $message = "<table>
                   <tr>
                       <td>Ground Name</td>
                       <td>".$invoiceDetail->groundName."</td>
                    </tr>
                    <tr>
                       <td>Address</td>
                       <td>".$invoiceDetail->groundAddress."</td>
                    </tr>
                    <tr>
                       <td>Booked Date</td>
                       <td>".$invoiceDetail->playDate."</td>
                    </tr>
                    <tr>
                       <td>Timings</td>
                       <td>".$invoiceDetail->groundName."</td>
                    </tr>
                    <tr>
                       <td>Booked By</td>
                       <td>".$invoiceDetail->userDetail->name."</td>
                    </tr>
                    <tr>
                       <td>Contact No</td>
                       <td>".$invoiceDetail->userDetail->phoneNumber."</td>
                    </tr>";
    $to =  $invoiceDetail->userDetail->email;
    $subject = "Play ground booking confirmation-".$invoiceDetail->invoiceNumber;
// Always set content-type when sending HTML email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

// More headers
    $headers .= 'From:support@bookyourground.com' . "\r\n";
    $headers .= 'Cc:erakesh.smile@gmail.com' . "\r\n";
    $res = mail($to,$subject,$message,$headers);

}



function retrieveInvoiceDetail($invoiceNumber)
{
    $result = new stdClass();
    try {
        $dbCon = getBygConnection();

        $sql = 'select InvoiceDetail from Invoice where InvoiceNumber =?';
        $stmt = $dbCon->prepare($sql);

        $stmt->bindParam(1, $invoiceNumber);

        $stmt->execute();

        $resultSet = $stmt->fetch(PDO::FETCH_ASSOC);

        $dbCon = null;
        if(!empty($resultSet)) {
            $result=  json_decode($resultSet['InvoiceDetail']);
        }

    } catch (PDOException $e) {
        echo '{"error":{"text":' . $e->getMessage() . '}}';
    }
    return $result;
}



/**
 * @param $paymentId
 * @return mixed
 */
function retrievePaymentDetails($paymentId)
{
    $url = "https://www.instamojo.com/api/1.1/payments/" . $paymentId;

    $headers = array(
        'http' => array(
            'method' => "GET",
            'header' => "X-Api-Key: " . 'f6b5704c42c1f1ad44467d4eed08371f' . "\r\n" .
                "X-Auth-Token: " . 'b5f158db16f50f7fe9094fc4904e163a'

        )
    );

// Creates a stream context
    $context = stream_context_create($headers);

// Open the URL with the HTTP headers (fopen wrappers must be enabled)
    $response = file_get_contents($url, false, $context);
    //echo $response;

    $json = json_decode($response);
    return $json;
}


function createPayment($request)
{
    try {
        $dbCon = getBygConnection();

        $sql = 'CALL Create_Payment(?,?,?,?,@paymentId)';
        $stmt = $dbCon->prepare($sql);

        $stmt->bindParam(1, $request->invoiceId);
        $stmt->bindParam(2, $request->userId);
        $stmt->bindParam(3, $request->paymentId);
        $stmt->bindParam(4, $request->paymentStatus);
        $stmt->execute();

        $resultSet = $dbCon->query("SELECT @paymentId")->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        $dbCon = null;

        $res = new stdClass();
        $res->paymentId = $resultSet['@paymentId'];

        return $res;

    } catch (PDOException $e) {
        echo '{"error":{"text":' . $e->getMessage() . '}}';
    }
}

function searchSportsGrounds()
{
    //date,location,    sportsName
    $date = isset($_GET["date"]) ? $_GET["date"] : date("Y-d-m");
    $location = isset($_GET["location"]) ? $_GET["location"] : "Bengaluru";
    $sportsName = isset($_GET["sportsName"]) ? $_GET["sportsName"] : "Cricket";

    try {
        $dbCon = getBygConnection();
        $sql = 'CALL GetSportsGroundsForPlayDay(?,?,?)';
        $stmt = $dbCon->prepare($sql);

        $stmt->bindParam(1, $location);

        $stmt->bindParam(2, $sportsName);
        $stmt->bindParam(3, $date);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_OBJ);

        $stmt->closeCursor();
        $dbCon = null;
        echo json_encode($results);
    } catch (PDOException $e) {
        echo '{"error":{"text":' . $e->getMessage() . '}}';
    }

}

function getSportsGroundSlots($groundId)
{
    $date = isset($_GET["date"]) ? $_GET["date"] : date("Y-d-m");
    $groundSlots = getGroundSlots($groundId, $date);
    $slotRates = getSlotsEffectiveRate($groundId, $date);

    $map = array();
    foreach ($slotRates as $value) {
        $map[$value->Slot] = $value;
    }
    foreach ($groundSlots as $slot) {
        $slot->rate = $map[$slot->Slot]->Rate;
    }
    $res = new stdClass();
    $res->bookedSlots = $groundSlots;
    $res->allRates = $slotRates;

    echo json_encode($res);
}

/**
 * @return array
 */
function getGroundSlots($groundId, $date)
{
    $results = [];
    try {
        $dbCon = getBygConnection();
        $sql = 'CALL GetSportsGroundBookings(?,?)';
        $stmt = $dbCon->prepare($sql);

        $stmt->bindParam(1, $groundId);

        $stmt->bindParam(2, $date);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_OBJ);

        $stmt->closeCursor();
        $dbCon = null;
        //echo json_encode($results);
    } catch (PDOException $e) {
        echo '{"error":{"text":' . $e->getMessage() . '}}';
    }
    return $results;
}

function getSlotsEffectiveRate($groundId, $date)
{
    $results = [];
    try {
        $dbCon = getBygConnection();
        $sql = 'CALL GetEffectiveRatesForGroundOnADay(?,?)';
        $stmt = $dbCon->prepare($sql);

        $stmt->bindParam(1, $groundId);

        $stmt->bindParam(2, $date);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_OBJ);

        $stmt->closeCursor();
        $dbCon = null;
        //echo json_encode($results);
    } catch (PDOException $e) {
        echo '{"error":{"text":' . $e->getMessage() . '}}';
    }
    return $results;
}

function createBooking($groundId, $slotId)
{
    global $app;
    $request = $app->request(); // Getting parameter with names
    $body = $request->getBody();
    $input = json_decode($body);

    try {
        $dbCon = getBygConnection();

        $sql = 'CALL Booking_CreateBooking(?,?,?,?,?,?,@bookingId)';
        $stmt = $dbCon->prepare($sql);

        $stmt->bindParam(1, $groundId);
        $stmt->bindParam(2, $input->bookingType);
        $stmt->bindParam(3, $input->webIp);
        $stmt->bindParam(4, $input->webSessionId);
        $stmt->bindParam(5, $input->date);
        $stmt->bindParam(6, $slotId);
        $stmt->execute();

        $resultSet = $dbCon->query("SELECT @bookingId")->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        $dbCon = null;
        $app->response()->header('Content-Type', 'application/json');
        $app->response()->header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");

        $res = new stdClass();
        $res->success = $resultSet['@bookingId'];
        echo json_encode($res);
    } catch (PDOException $e) {
        echo '{"error":{"text":' . $e->getMessage() . '}}';
    }
}

function addSlotToBooking($bookingId)
{
    global $app;
    $request = $app->request(); // Getting parameter with names
    $body = $request->getBody();
    $input = json_decode($body);

    try {
        $dbCon = getBygConnection();

        $sql = 'CALL Booking_AddBookingSlot(?,?,?)';
        $stmt = $dbCon->prepare($sql);

        $stmt->bindParam(1, $bookingId);
        $stmt->bindParam(2, $input->date);
        $stmt->bindParam(3, $input->slot);
        $stmt->execute();

        $stmt->closeCursor();
        $dbCon = null;
        $app->response()->header('Content-Type', 'application/json');
        $app->response()->header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");

        $res = new stdClass();
        $res->success = "success";
        echo json_encode($res);
    } catch (PDOException $e) {
        echo '{"error":{"text":' . $e->getMessage() . '}}';
    }
}

function cancelSlotFromBooking($bookingId, $slotId)
{

    global $app;
    $request = $app->request(); // Getting parameter with names
    $body = $request->getBody();
    $input = json_decode($body);

    try {
        $dbCon = getBygConnection();

        $sql = 'CALL Booking_CancelBookingSlot(?,?,?)';
        $stmt = $dbCon->prepare($sql);

        $stmt->bindParam(1, $bookingId);
        $stmt->bindParam(2, $input->date);
        $stmt->bindParam(3, $slotId);
        $stmt->execute();

        $stmt->closeCursor();
        $dbCon = null;
        $app->response()->header('Content-Type', 'application/json');
        $app->response()->header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");

        $res = new stdClass();
        $res->success = "success";
        echo json_encode($res);
    } catch (PDOException $e) {
        echo '{"error":{"text":' . $e->getMessage() . '}}';
    }

}

function removeSlotFromBooking($bookingId, $slotId)
{
    global $app;
    $request = $app->request(); // Getting parameter with names
    $body = $request->getBody();
    $input = json_decode($body);

    try {
        $dbCon = getBygConnection();

        $sql = 'CALL Booking_RemoveBookingSlot(?,?,?)';
        $stmt = $dbCon->prepare($sql);

        $stmt->bindParam(1, $bookingId);
        $stmt->bindParam(2, $input->date);
        $stmt->bindParam(3, $slotId);
        $stmt->execute();

        $stmt->closeCursor();
        $dbCon = null;
        $app->response()->header('Content-Type', 'application/json');
        $app->response()->header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");

        $res = new stdClass();
        $res->success = "success";
        echo json_encode($res);
    } catch (PDOException $e) {
        echo '{"error":{"text":' . $e->getMessage() . '}}';
    }

}

//IN inBookingID INTEGER,
//IN inBookingType ENUM('ONLINE', 'ADMIN'),IN inUserName VARCHAR(45),IN inUserEmail VARCHAR(45),IN inPhoneNumber VARCHAR(45),OUT OutUserID INT(11)
//IN inBookingID INTEGER,
 //IN inBookingType ENUM('ONLINE', 'ADMIN'),IN inUserName VARCHAR(45),IN inUserEmail VARCHAR(45),IN inPhoneNumber VARCHAR(45),
//IN inbookingAmt DECIMAL(12,2),IN inTotalAmount DECIMAL(12,2),IN inPaymentFee DECIMAL(12,2),
//OUT OutUserID INT(11),OUT outInvoiceNumber VARCHAR(64))
function confirmBooking($bookingId)
{
    global $app;
    $request = $app->request(); // Getting parameter with names
    $body = $request->getBody();
    $input = json_decode($body);

    try {
        $dbCon = getBygConnection();

        $sql = 'CALL Booking_ConfirmBookingWithUser(?,?,?,?,?,?,?,?,?,@userId,@invoiceNumber)';
        $stmt = $dbCon->prepare($sql);

        $stmt->bindParam(1, $bookingId);
        $stmt->bindParam(2, $input->bookingType);
        $stmt->bindParam(3, $input->userName);
        $stmt->bindParam(4, $input->userEmail);
        $stmt->bindParam(5, $input->phoneNumber);
        $stmt->bindParam(6, $input->bookingAmount);
        $stmt->bindParam(7, $input->totalAmount);
        $stmt->bindParam(8, $input->paymentFee);
        $stmt->bindValue(9, json_encode($input->invoiceDetails));


        $stmt->execute();
        $userIds = $dbCon->query("SELECT @userId")->fetch(PDO::FETCH_ASSOC);
        $invoiceNumber = $dbCon->query("SELECT @invoiceNumber")->fetch(PDO::FETCH_ASSOC);

        $stmt->closeCursor();
        $dbCon = null;
        $app->response()->header('Content-Type', 'application/json');
        $app->response()->header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");

        $res = new stdClass();
        $res->userId = $userIds['@userId'];
        $res->invoiceNumber = $invoiceNumber['@invoiceNumber'];

        echo json_encode($res);

    } catch (PDOException $e) {
        echo '{"error":{"text":' . $e->getMessage() . '}}';
    }
}


function getUsers()
{
    $sql_query = "select `name`,`email`,`date`,`ip` FROM restAPI ORDER BY name";
    try {
        $dbCon = getConnection();
        $stmt = $dbCon->query($sql_query);
        $users = $stmt->fetchAll(PDO::FETCH_OBJ);
        $dbCon = null;
        echo '{"users": ' . json_encode($users) . '}';
    } catch (PDOException $e) {
        echo '{"error":{"text":' . $e->getMessage() . '}}';
    }

}

function getUser($id)
{
    $sql = "SELECT `name`,`email`,`date`,`ip` FROM restAPI WHERE id=:id";
    try {
        $dbCon = getConnection();
        $stmt = $dbCon->prepare($sql);
        $stmt->bindParam("id", $id);
        $stmt->execute();
        $user = $stmt->fetchObject();
        $dbCon = null;
        echo json_encode($user);
    } catch (PDOException $e) {
        echo '{"error":{"text":' . $e->getMessage() . '}}';
    }
}

function addUser()
{
    global $app;
    $req = $app->request(); // Getting parameter with names
    $paramName = $req->params('name'); // Getting parameter with names
    $paramEmail = $req->params('email'); // Getting parameter with names

    $sql = "INSERT INTO restAPI (`name`,`email`,`ip`) VALUES (:name, :email, :ip)";
    try {
        $dbCon = getConnection();
        $stmt = $dbCon->prepare($sql);
        $stmt->bindParam("name", $paramName);
        $stmt->bindParam("email", $paramEmail);
        $stmt->bindParam("ip", $_SERVER['REMOTE_ADDR']);
        $stmt->execute();
        $user = $dbCon->lastInsertId();
        $dbCon = null;
        echo json_encode($user);
    } catch (PDOException $e) {
        echo '{"error":{"text":' . $e->getMessage() . '}}';
    }
}

function updateUser($id)
{
    global $app;
    $req = $app->request();
    $paramName = $req->params('name');
    $paramEmail = $req->params('email');

    $sql = "UPDATE restAPI SET name=:name, email=:email WHERE id=:id";
    try {
        $dbCon = getConnection();
        $stmt = $dbCon->prepare($sql);
        $stmt->bindParam("name", $paramName);
        $stmt->bindParam("email", $paramEmail);
        $stmt->bindParam("id", $id);
        $status = $stmt->execute();

        $dbCon = null;
        echo json_encode($status);
    } catch (PDOException $e) {
        echo '{"error":{"text":' . $e->getMessage() . '}}';
    }
}

function deleteUser($id)
{
    $sql = "DELETE FROM restAPI WHERE id=:id";
    try {
        $dbCon = getConnection();
        $stmt = $dbCon->prepare($sql);
        $stmt->bindParam("id", $id);
        $status = $stmt->execute();
        $dbCon = null;
        echo json_encode($status);
    } catch (PDOException $e) {
        echo '{"error":{"text":' . $e->getMessage() . '}}';
    }
}

function findByName($query)
{
    $sql = "SELECT * FROM restAPI WHERE UPPER(name) LIKE :query ORDER BY name";
    try {
        $dbCon = getConnection();
        $stmt = $dbCon->prepare($sql);
        $query = "%" . $query . "%";
        $stmt->bindParam("query", $query);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_OBJ);
        $dbCon = null;
        echo '{"user": ' . json_encode($users) . '}';
    } catch (PDOException $e) {
        echo '{"error":{"text":' . $e->getMessage() . '}}';
    }
}

function getConnection()
{
    try {
        $db_username = "root";
        $db_password = "";
        $conn = new PDO('mysql:host=localhost;dbname=rest', $db_username, $db_password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    } catch (PDOException $e) {
        echo 'ERROR: ' . $e->getMessage();
    }
    return $conn;
}

function getBygConnection()
{
    try {
        $db_username = "root";
        $db_password = "";
        $conn = new PDO('mysql:host=localhost;dbname=byg_2015', $db_username, $db_password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    } catch (PDOException $e) {
        echo 'ERROR: ' . $e->getMessage();
    }
    return $conn;
}


?>
