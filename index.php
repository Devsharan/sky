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
        echo json_encode($results);
    } catch (PDOException $e) {
        echo '{"error":{"text":' . $e->getMessage() . '}}';
    }
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
        echo json_encode($resultSet['@bookingId']);
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

        echo json_encode("success");
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

        echo json_encode("success");
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

        echo json_encode("success");
    } catch (PDOException $e) {
        echo '{"error":{"text":' . $e->getMessage() . '}}';
    }

}

function confirmBooking($bookingId)
{
    global $app;
    $request = $app->request(); // Getting parameter with names
    $body = $request->getBody();
    $input = json_decode($body);

    try {
        $dbCon = getBygConnection();

        $sql = 'CALL Booking_ConfirmBookingWithUser(?,?,?,?,?,@userId)';
        $stmt = $dbCon->prepare($sql);

        $stmt->bindParam(1, $bookingId);
        $stmt->bindParam(2, $input->bookingType);
        $stmt->bindParam(3, $input->userName);
        $stmt->bindParam(4, $input->userEmail);
        $stmt->bindParam(5, $input->phoneNumber);
        $stmt->execute();
        $resultSet = $dbCon->query("SELECT @userId")->fetch(PDO::FETCH_ASSOC);

        $stmt->closeCursor();
        $dbCon = null;
        $app->response()->header('Content-Type', 'application/json');
        $app->response()->header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
        echo json_encode($resultSet['@userId']);
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
