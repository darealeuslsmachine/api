<?php 

class Database
{
    private $link;

    public function __construct() //конструктор срабатывает при создании класса
    {
        $this->connect();
    }
    private function connect() //устанавливает соединение с бд
    {
        $config = require_once 'config.php';

        $dsn = 'mysql:host='.$config['host'].';dbname='.$config['db_name'].';charset='.$config['charset'].';';        

        $this->link = new PDO($dsn, $config['username'], $config['password']);

        return $this;
    }
    public function execute($sql)//подготавливает, возвращает идентификатор запроса
    {
        $sth = $this->link->prepare($sql);

        return $sth->execute();
    }

    public function query($sql) //выполняет sql запрос(получает ассоциативный массив или возвращает пустой)
    {
        $sth = $this->link->prepare($sql);

        $sth->execute();

        $result = $sth->fetchAll(PDO::FETCH_ASSOC);

        if($result === false){
            return [];
        }
        return $result;
    }
}


$out = array('status_answer' => '', 'status_message' => '', 'out' => '');


/*
* Db obj
*/
try{
    $db = new Database();
} catch(PDOException $e){
    $out['status_answer'] = 'error';
    $out['status_message'] = $e->getMessage();
    $json = json_encode($out);
    echo $json;
    exit();
}
$users = $db->query("SELECT * FROM `login`");//получение массива с данными о юзерах



/*
* Login
*/
if((isset($_GET['login'])) && isset($_GET['passwd'])){
    
    session_start();
    
    $_SESSION["user"]=0;
    $username = $_GET['login'];
    $password = $_GET['passwd'];
    if($username!="" && $password!="") {
        if(array_search($username, array_column($users, 'login'))===false){
            
            $out['status_answer'] = 'error';
            $out['status_message'] = 'Invalid login or password!';
            $json = json_encode($out);
            echo $json;
            $db = null;
            session_destroy(); 
            exit;

        }
        else{
            $key = array_search($username, array_column($users, 'login'));
            if (array_search($password,$users[$key])===false){
                
                $out['status_answer'] = 'error';
                $out['status_message'] = 'Invalid login or password!';
                $json = json_encode($out);
                echo $json;
                $db = null; 
                session_destroy();                  
                exit;
            }
            else{  
                $_SESSION["user"]=1;
                date_default_timezone_set("UTC");
                $time = time(); 
                $offset = 3; 
                $time += 3 * 3600;     
                $auth = gmdate("y-m-d H:i:s", $time);
                $id = $users[$key]['id'];                               
                $db->execute("UPDATE `login` SET `last_auth`='$auth' WHERE `id`='$id'");                             
            }
        }
    }
}



$workers = $db->query("SELECT * FROM `employeetable`");//получение массива с данными о работниках



function errEmpNotFound(){
    $out['status_answer'] = 'error';
    $out['status_message'] = 'Worker not found!';
    $json = json_encode($out);
    echo $json;
}


function successfulRequest(){
    $out['status_answer'] = 'ok';
    $out['out'] = 'A successful request!';
    $json = json_encode($out);
    echo $json;
}



/*
* workers in JSON
*/
if(isset($_GET['method']) && ($_GET['method']==='workers') && ($_SESSION["user"]=1)){
    
    if (empty($workers)===false){
        $out['status_answer'] = 'ok';   
        $out['out'] = $workers; 
        $out = json_encode($out, JSON_UNESCAPED_UNICODE); 
        echo $out;
    } else {
        errEmpNotFound();
    }
   
    $db = null; 
    session_destroy();
    exit;  
}



/*
* workers with only id
*/
if(isset($_GET['method']) && ($_GET['method']==='workersid') && ($_SESSION["user"]=1)){   
    
    if (empty($workers)===false){        
        
        $workersResult = [];

        for ($i=0;$i<count($workers);$i++){
            $emplArr = $workers[$i];
            $emplInfo = "$emplArr[id]: $emplArr[lastname] $emplArr[firstname] $emplArr[patronymic];";
            array_push($workersResult, $emplInfo);        
        }
        
        $out['status_answer'] = 'ok';   
        $out['out'] = $workersResult; 
        $out = json_encode($out, JSON_UNESCAPED_UNICODE); 
        echo $out;
    } else {
        errEmpNotFound();
    }
   
   
    $db = null; 
    session_destroy();
    exit; 
}



/*
* offices
*/
if(isset($_GET['method']) && ($_GET['method']==='offices') && ($_SESSION["user"]=1)){  
    
    $offices = $db->query("SELECT office FROM employeetable");//получение массива с данными о офисах     
    
    if (empty($offices)===false){
        $arrOffices = [];
        $arrOfficesWId = [];
        foreach($offices as $val){
            (array_push($arrOffices, $val['office']));
        }
        $arrOffices = array_unique($arrOffices);
        $arrOffices = array_diff($arrOffices, array(""));    
        $arrOffices = array_values($arrOffices);  
        
        $out['status_answer'] = 'ok';   
        $out['out'] = $arrOffices;        
        $out = json_encode($out, JSON_UNESCAPED_UNICODE); 
        echo $out;    
    }
    
    $db = null; 
    session_destroy();
    exit;
}



/*
* UPDATE
*/
if(isset($_GET['method']) && ($_GET['method']==='update') && ($_SESSION["user"]=1)){
    $id = $_GET['id'];
    $_SESSION["worker"] = 0;  

    if (array_search($id, array_column($workers, 'id'))!==false){
        if(isset($_GET['fio'])){
            $fio = $_GET['fio'];
            $name = explode(" ", $fio);
            $lastname = $name[0];
            $firstame = $name[1];            
            $patronymic = $name[2];
            $db->execute("UPDATE `employeetable` SET `firstname`='$firstame', `lastname`='$lastname', `patronymic`='$patronymic' WHERE `id` = '$id'"); 
            $_SESSION["worker"] = 1;           
        }             
        
        if(isset($_GET['address'])){
            $address = $_GET['address'];
            $db->execute("UPDATE `employeetable` SET `address`='$address' WHERE `id` = '$id'");
            $_SESSION["worker"] = 1;
        }    
        
        if(isset($_GET['phone'])){
            $phone = $_GET['phone'];
            $db->execute("UPDATE `employeetable` SET `phoneNumbers`='$phone' WHERE `id` = '$id'");
            $_SESSION["worker"] = 1;
        } 
    
        if(isset($_GET['post'])){
            $post = $_GET['post'];
            $db->execute("UPDATE `employeetable` SET `post`='$post' WHERE `id` = '$id'"); 
            $_SESSION["worker"] = 1;
        } 

        if(isset($_GET['office'])){
            $office = $_GET['office'];
            $db->execute("UPDATE `employeetable` SET `office`='$office' WHERE `id` = '$id'"); 
            $_SESSION["worker"] = 1;
        }     
    } 
    
    
    if ($_SESSION["worker"] === 0){
        errEmpNotFound();
    } else {
        successfulRequest(); 
    }
    
    
    $db = null; 
    session_destroy();
    exit;  
}



/*
* INSERT
*/
if(isset($_GET['method']) && ($_GET['method']==='insert') && ($_SESSION["user"]=1)){        
   
    if(isset($_GET['fio'])){
        $fio = $_GET['fio'];
        $name = explode(" ", $fio);
        $firstame = $name[1];
        $lastname = $name[0];
        $patronymic = $name[2];    
    } else { 
        $out['status_answer'] = 'error';
        $out['status_message'] = 'Fio not found!';
        $json = json_encode($out);
        echo $json;
        $db = null; 
        session_destroy();
        exit;            
    }
    
    if(isset($_GET['address'])){
        $address = $_GET['address'];
    } else {
        $address = '';
    }    
    
    if(isset($_GET['phone'])){
        $phone = $_GET['phone'];
    } else {
        $phone = '';
    }

    if(isset($_GET['post'])){
        $post = $_GET['post'];
    } else {
        $post = '';
    }

    if(isset($_GET['office'])){
        $office = $_GET['office'];
    } else {
        $office = '';
    }

    
    $db->execute("INSERT INTO `employeetable` SET `firstname`='$firstame', `lastname`='$lastname', `patronymic`='$patronymic', `address`='$address', `phoneNumbers`='$phone', `post`='$post', `office`='$office'");
    successfulRequest(); 
    
    
    $db = null; 
    session_destroy();
    exit;
}



/*
* DELETE
*/
if(isset($_GET['method']) && (isset($_GET['id'])) && ($_GET['method']==='delete') && ($_SESSION["user"]=1)){  
    $id = $_GET['id'];
    if(array_search($id, array_column($workers, 'id'))!==false){       
        $db->query("DELETE FROM `employeetable` WHERE `id`='$id'");
        successfulRequest();
    } else {
        errEmpNotFound();
    }
    $db = null; 
    session_destroy();
    exit;    
}



/*
* SEARCH
*/
if(isset($_GET['method']) && ($_GET['method']==='search') && ($_SESSION["user"]=1) && (isset($_GET['fio']))){         
    $fio = $_GET['fio'];
    $_SESSION["worker"] = 0;  

    $searchResult = [];

    //for 1 word
    if (strpos($fio, ' ')===false){        
        foreach ($workers as $worker){  
            if (array_search($fio, $worker) !== false) {
                array_push($searchResult, $worker);             
                $_SESSION["worker"] = 1;
            }   
        }                
    } 
    
    //for 2 words
    elseif (substr_count($fio, " ")===1) { 
        $name = explode(" ", $fio);
        $firstame = $name[1];
        $lastname = $name[0];          
       
        foreach ($workers as $worker){            
            if ((array_search($firstame, $worker)!== false) && (array_search($lastname, $worker)!== false)){                
                array_push($searchResult, $worker);  
                $_SESSION["worker"] = 1;
            }
        }       
    }
    
    //for 3 words
    elseif (substr_count($fio, " ")===2) {
        $name = explode(" ", $fio);
        $firstame = $name[1];
        $lastname = $name[0];
        $patronymic = $name[2];   
       
        foreach ($workers as $worker){            
            if ((array_search($firstame, $worker)!== false) && (array_search($lastname, $worker)!== false) && (array_search($patronymic, $worker)!== false)){                
                array_push($searchResult, $worker);  
                $_SESSION["worker"] = 1;
            }
        }       
    }


    if ($_SESSION["worker"] === 0){
        errEmpNotFound();
    } else {
        $out['status_answer'] = 'ok';   
        $out['out'] = $searchResult; 
        $out = json_encode($out, JSON_UNESCAPED_UNICODE); 
        echo $out;  
    }
    
    
    
    $db = null; 
    session_destroy();
    exit;     
}    