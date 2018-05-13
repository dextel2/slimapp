<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app = new \Slim\App;

$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

$app->add(function ($req, $res, $next) {
    $response = $next($req, $res);
    return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});

// Get All Candidates
$app->get('/api/getCandidates', function(Request $request, Response $response){
    $sql = "SELECT * FROM userregistration WHERE isActive=1 AND role='Candidate'";

    try{
        // Get DB Object
        $db = new db();
        // Connect
        $db = $db->connect();

        $stmt = $db->query($sql);
        $customers = $stmt->fetchAll(PDO::FETCH_OBJ);
        $db = null;
        echo json_encode($customers);
    } catch(PDOException $e){
        echo '{"error": {"text": '.$e->getMessage().'}';
    }
});


// Get Single Candidate
$app->get('/api/getCandidateById/{id}', function(Request $request, Response $response){
    $id = $request->getAttribute('id');

    $sql = "SELECT * FROM userregistration WHERE UID = $id AND isActive=1 AND role='Candidate'";

    try{
        // Get DB Object
        $db = new db();
        // Connect
        $db = $db->connect();

        $stmt = $db->query($sql);
        $customer = $stmt->fetch(PDO::FETCH_OBJ);
        $db = null;
        echo json_encode($customer);
    } catch(PDOException $e){
        echo '{"error": {"text": '.$e->getMessage().'}';
    }
});



// Get Saved Jobs
$app->get('/api/getSavedJobs/{id}', function(Request $request, Response $response){
    $userId = $request->getAttribute('id');

    $sql = "SELECT job_details.job_name,job_details.company_name FROM job_details
    JOIN
    asjobs
    ON
    asjobs.jd_ID = job_details.jd_ID
    WHERE asjobs.isSaved=1 and asjobs.isActive=1 and asjobs.UID = $userId";
    
    try{
        // Get DB Object
        $db = new db();
        // Connect
        $db = $db->connect();

        $stmt = $db->query($sql);
        $customer = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $db = null;
        if($customer) {
            echo json_encode($customer);
        }
        else {
            echo '{"notfound":"404"}';
        }

    } catch(PDOException $e){
        echo '{"error": {"text": '.$e->getMessage().'}';
    }
});

//Get Jobs to Apply
$app->get('/api/getJobs/', function(Request $request, Response $response){
    //$userId = $request->getAttribute('id');

    $sql = "SELECT jd_ID,job_name, company_name FROM job_details";
    
    try{
        // Get DB Object
        $db = new db();
        // Connect
        $db = $db->connect();

        $stmt = $db->query($sql);
        $customer = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $db = null;
        if($customer) {
            echo json_encode($customer);
        } else {
            echo '{"notfound":"404"}';
        }

    } catch(PDOException $e){
        echo '{"error": {"text": '.$e->getMessage().'}';
    }
});



// Get Candidate personal details
$app->get('/api/getCandidatePersonalDetails/{id}', function(Request $request, Response $response){
    $id = $request->getAttribute('id');

    $sql = "SELECT personal_details.dob,userregistration.fullname,personal_details.c_add,
                    personal_details.s_email,personal_details.c_con
            FROM personal_details 
            INNER JOIN userregistration 
            ON 
            personal_details.UID = $id";
    
    try{
        // Get DB Object
        $db = new db();
        // Connect
        $db = $db->connect();

        $stmt = $db->query($sql);
        $customer = $stmt->fetch(PDO::FETCH_OBJ);
        $db = null;
        echo json_encode($customer);
    } catch(PDOException $e){
        echo '{"error": {"text": '.$e->getMessage().'}';
    }
});


// return login
$app->post('/api/login/', function(Request $request, Response $response){
    
    try{
        $email = $request->getParam('email');
        $password = $request->getParam('password');
        
        // Get DB Object
        $db = new db();
        
        // Connect
        $sql = "SELECT password,UID FROM userregistration WHERE email = ?";

        $db = $db->connect();
        $stmt = $db->prepare($sql);
        $stmt->bindParam(1, $email);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $hash = $data['password'];
        
        if(password_verify($password,$hash)){
        if($stmt->rowCount() > 0){
            echo '{"success":"200","ID":"'.$data['UID'].'"}';
        }
        else {
            echo '{"unauth":"500"}';
        }
    }
    
        else {
            echo '{"notfound":"404"}';
        }

    } catch(PDOException $e){
        echo '{"error": {"text": '.$e->getMessage().'}';
    }
});

/**
 * {Post} Apply Job : User Applies for Job
 * @param : jd_id
 * @param : UID
 * @param : companyID
 */

$app->post('/api/apply/', function(Request $request, Response $response){
    
    $jd_ID = $request->getParam('jd_ID');
	$UID = $request->getParam('UID');
	$CompanyID = $request->getParam('CompanyID');

    $sql = "INSERT INTO asjobs (jd_ID,UID,isSaved,isApplied,c_date,isActive,CompanyID) VALUES (?,?,0,1,NOW(),1,?)";

    try {

        $db = new db();
        $db = $db->connect();
        $stmt = $db->prepare($sql);
        $stmt->bindParam(1,$jd_ID);
		$stmt->bindParam(2,$UID);
		$stmt->bindParam(3,$CompanyID);
        
        $stmt->execute();
        echo '{"success": "200"}';
    }
    catch(PDOExeception $pdo){
        echo '{"error": {"text": '. $pdo->getMessage().'}';
    }
});


/**
 * {PUT} Update Password : Update password functionality for the user
 * @param : userid
 * @param : oldpassword
 * @param : newpassword
 * @param : confirmpassword
 * 
 * TODO:    bring old password as well from the user
 * FIXME:   Update query such the job execution is done in only one query
 *          rather than 2 seperate queries
 */
$app->put('/api/updatepwd/{id}', function(Request $request, Response $response){
    $id = $request->getAttribute("id");
    $password = $request->getParam("password");
    $confirmPassword = $request->getParam("confirmPassword");
	
	if($password==$confirmPassword) {
    	$password = password_hash($password,PASSWORD_BCRYPT);
    	$password = str_replace("$2y$","$2a$",$password);
    	$sql = "UPDATE userregistration SET `password` = ? where UID=$id";
    		try {
        		// Get DB Object
        		$db = new db();
        		// Connect
        		$db = $db->connect();
        		$stmt = $db->prepare($sql);
        		$stmt->bindParam(1,$password);
        		$stmt->execute();
        		echo '{"status":"200"}';
    	} catch(PDOException $e){
				echo '{"error": {"text": '.$e->getMessage().'}';
		}
	}
	else {
    	echo '{"status":"400"}';
	}
});


//User Feedback
$app->post('/api/feedback/', function(Request $request, Response $response){
    
    $uid = $request->getParam('uid');
    $message = $request->getParam('message');
    $sql = "INSERT INTO userfeedback (UID,message) VALUES (?,?)";

    try {
        // Get DB Object
        $db = new db();
        // Connect
        $db = $db->connect();
        $stmt = $db->prepare($sql);
        $stmt->bindParam(1,$uid);
        $stmt->bindParam(2,$message);
        
        $stmt->execute();
        echo '{"success": "200"}';
    }
    catch(PDOExeception $pdo){
        echo '{"error": {"text": '. $pdo->getMessage().'}';
    }
});

// Add Customer
$app->post('/api/customer/add', function(Request $request, Response $response){
    $first_name = $request->getParam('first_name');
    $last_name = $request->getParam('last_name');
    $phone = $request->getParam('phone');
    $email = $request->getParam('email');
    $address = $request->getParam('address');
    $city = $request->getParam('city');
    $state = $request->getParam('state');

    $sql = "INSERT INTO customers (first_name,last_name,phone,email,address,city,state) VALUES
    (:first_name,:last_name,:phone,:email,:address,:city,:state)";

    try{
        // Get DB Object
        $db = new db();
        // Connect
        $db = $db->connect();

        $stmt = $db->prepare($sql);

        $stmt->bindParam(':first_name', $first_name);
        $stmt->bindParam(':last_name',  $last_name);
        $stmt->bindParam(':phone',      $phone);
        $stmt->bindParam(':email',      $email);
        $stmt->bindParam(':address',    $address);
        $stmt->bindParam(':city',       $city);
        $stmt->bindParam(':state',      $state);

        $stmt->execute();

        echo '{"notice": {"text": "Customer Added"}';

    } catch(PDOException $e){
        echo '{"error": {"text": '.$e->getMessage().'}';
    }
});






// Update Customer
$app->put('/api/customer/update/{id}', function(Request $request, Response $response){
    $id = $request->getAttribute('id');
    $first_name = $request->getParam('first_name');
    $last_name = $request->getParam('last_name');
    $phone = $request->getParam('phone');
    $email = $request->getParam('email');
    $address = $request->getParam('address');
    $city = $request->getParam('city');
    $state = $request->getParam('state');

    $sql = "UPDATE customers SET
				first_name 	= :first_name,
				last_name 	= :last_name,
                phone		= :phone,
                email		= :email,
                address 	= :address,
                city 		= :city,
                state		= :state
			WHERE id = $id";

    try{
        // Get DB Object
        $db = new db();
        // Connect
        $db = $db->connect();

        $stmt = $db->prepare($sql);

        $stmt->bindParam(':first_name', $first_name);
        $stmt->bindParam(':last_name',  $last_name);
        $stmt->bindParam(':phone',      $phone);
        $stmt->bindParam(':email',      $email);
        $stmt->bindParam(':address',    $address);
        $stmt->bindParam(':city',       $city);
        $stmt->bindParam(':state',      $state);

        $stmt->execute();

        echo '{"notice": {"text": "Customer Updated"}';

    } catch(PDOException $e){
        echo '{"error": {"text": '.$e->getMessage().'}';
    }
});

// Delete Customer
$app->delete('/api/customer/delete/{id}', function(Request $request, Response $response){
    $id = $request->getAttribute('id');

    $sql = "DELETE FROM customers WHERE id = $id";

    try{
        // Get DB Object
        $db = new db();
        // Connect
        $db = $db->connect();

        $stmt = $db->prepare($sql);
        $stmt->execute();
        $db = null;
        echo '{"notice": {"text": "Customer Deleted"}';
    } catch(PDOException $e){
        echo '{"error": {"text": '.$e->getMessage().'}';
    }
});