<?php
require_once('includes/load.php');

/*--------------------------------------------------------------*/
/* FIND ALL
/*--------------------------------------------------------------*/
function find_all($table){
  global $db;
  if(tableExists($table)){
    return find_by_sql("SELECT * FROM ".$db->escape($table));
  }
  return [];
}

/*--------------------------------------------------------------*/
function find_by_sql($sql){
  global $db;
  $result = $db->query($sql);
  return $result ? $db->while_loop($result) : [];
}

/*--------------------------------------------------------------*/
function find_by_id($table,$id){
  global $db;
  $id = (int)$id;
  if(tableExists($table)){
    $sql = $db->query(
      "SELECT * FROM {$db->escape($table)} WHERE id='{$db->escape($id)}' LIMIT 1"
    );
    if($sql && $row = $db->fetch_assoc($sql)){
      return $row;
    }
  }
  return null;
}

/*--------------------------------------------------------------*/
function delete_by_id($table,$id){
  global $db;
  if(tableExists($table)){
    $sql = "DELETE FROM ".$db->escape($table)." WHERE id=".$db->escape($id)." LIMIT 1";
    $db->query($sql);
    return ($db->affected_rows() === 1);
  }
  return false;
}

/*--------------------------------------------------------------*/
function count_by_id($table){
  global $db;
  if(tableExists($table)){
    $sql = "SELECT COUNT(id) AS total FROM ".$db->escape($table);
    $result = $db->query($sql);
    return $result ? $db->fetch_assoc($result) : ['total'=>0];
  }
  return ['total'=>0];
}

/*--------------------------------------------------------------*/
function tableExists($table){
  global $db;
  $sql = "SHOW TABLES FROM ".DB_NAME." LIKE '".$db->escape($table)."'";
  $table_exit = $db->query($sql);
  return ($table_exit && $db->num_rows($table_exit) > 0);
}

/*--------------------------------------------------------------*/
function authenticate($username='', $password=''){
  global $db;
  $username = $db->escape($username);
  $password = $db->escape($password);

  $sql = "SELECT id,username,password,user_level FROM users WHERE username='{$username}' LIMIT 1";
  $result = $db->query($sql);

  if($result && $db->num_rows($result)){
    $user = $db->fetch_assoc($result);
    if(sha1($password) === $user['password']){
      return $user['id'];
    }
  }
  return false;
}

/*--------------------------------------------------------------*/
function authenticate_v2($username='', $password=''){
  global $db;
  $username = $db->escape($username);
  $password = $db->escape($password);

  $sql = "SELECT id,username,password,user_level FROM users WHERE username='{$username}' LIMIT 1";
  $result = $db->query($sql);

  if($result && $db->num_rows($result)){
    $user = $db->fetch_assoc($result);
    if(sha1($password) === $user['password']){
      return $user;
    }
  }
  return false;
}

/*--------------------------------------------------------------*/
function current_user(){
  static $current_user;
  if(!$current_user && isset($_SESSION['user_id'])){
    $user = find_by_id('users',(int)$_SESSION['user_id']);
    $current_user = is_array($user) ? $user : null;
  }
  return $current_user;
}

/*--------------------------------------------------------------*/
function find_all_user(){
  $sql = "SELECT u.id,u.name,u.username,u.user_level,u.status,u.last_login,
          g.group_name
          FROM users u
          LEFT JOIN user_groups g ON g.group_level=u.user_level
          ORDER BY u.name ASC";
  return find_by_sql($sql);
}

/*--------------------------------------------------------------*/
function updateLastLogIn($user_id){
  global $db;
  $date = make_date();
  $sql = "UPDATE users SET last_login='{$date}' WHERE id='{$user_id}' LIMIT 1";
  $db->query($sql);
  return ($db->affected_rows() === 1);
}

/*--------------------------------------------------------------*/
function find_by_groupName($val){
  global $db;
  $sql = "SELECT group_name FROM user_groups WHERE group_name='{$db->escape($val)}' LIMIT 1";
  $result = $db->query($sql);
  return ($db->num_rows($result) === 0);
}

/*--------------------------------------------------------------*/
function find_by_groupLevel($level){
  global $db;
  $sql = "SELECT * FROM user_groups WHERE group_level='".(int)$level."' LIMIT 1";
  $result = $db->query($sql);
  return ($result && $db->num_rows($result)) ? $db->fetch_assoc($result) : null;
}

/*--------------------------------------------------------------*/
function page_require_level($require_level){
  global $session;

  $user = current_user();
  if(!$user){
    $session->msg('d','Please login...');
    redirect('index.php',false);
  }

  $group = find_by_groupLevel($user['user_level']);
  if(!$group || $group['group_status'] === '0'){
    $session->msg('d','Access denied');
    redirect('home.php',false);
  }

  if((int)$user['user_level'] <= (int)$require_level){
    return true;
  }

  $session->msg('d','Sorry! you dont have permission.');
  redirect('home.php',false);
}

/*--------------------------------------------------------------*/
function join_product_table(){
  $sql = "SELECT p.id,p.name,p.quantity,p.buy_price,p.sale_price,
          p.media_id,p.date,c.name AS categorie,m.file_name AS image
          FROM products p
          LEFT JOIN categories c ON c.id=p.categorie_id
          LEFT JOIN media m ON m.id=p.media_id
          ORDER BY p.id ASC";
  return find_by_sql($sql);
}

/*--------------------------------------------------------------*/
function find_product_by_title($product_name){
  global $db;
  $name = remove_junk($db->escape($product_name));
  $sql = "SELECT name FROM products WHERE name LIKE '%{$name}%' LIMIT 5";
  return find_by_sql($sql);
}

/*--------------------------------------------------------------*/
function find_all_product_info_by_title($title){
  $sql = "SELECT * FROM products WHERE name='{$title}' LIMIT 1";
  return find_by_sql($sql);
}

/*--------------------------------------------------------------*/
function update_product_qty($qty,$p_id){
  global $db;
  $sql = "UPDATE products SET quantity=quantity-".(int)$qty." WHERE id=".(int)$p_id;
  $db->query($sql);
  return ($db->affected_rows() === 1);
}

/*--------------------------------------------------------------*/
function find_recent_product_added($limit){
  global $db;
  $sql = "SELECT p.id,p.name,p.sale_price,p.media_id,
          c.name AS categorie,m.file_name AS image
          FROM products p
          LEFT JOIN categories c ON c.id=p.categorie_id
          LEFT JOIN media m ON m.id=p.media_id
          ORDER BY p.id DESC LIMIT ".(int)$limit;
  return find_by_sql($sql);
}

/*--------------------------------------------------------------*/
function find_higest_saleing_product($limit){
  global $db;
  $sql = "SELECT p.name,COUNT(s.product_id) AS totalSold,SUM(s.qty) AS totalQty
          FROM sales s
          LEFT JOIN products p ON p.id=s.product_id
          GROUP BY s.product_id
          ORDER BY totalQty DESC LIMIT ".(int)$limit;
  $result = $db->query($sql);
  return $result ? $db->while_loop($result) : [];
}

/*--------------------------------------------------------------*/
function find_all_sale(){
  $sql = "SELECT s.id,s.qty,s.price,s.date,p.name
          FROM sales s
          LEFT JOIN products p ON s.product_id=p.id
          ORDER BY s.date DESC";
  return find_by_sql($sql);
}

/*--------------------------------------------------------------*/
function find_recent_sale_added($limit){
  global $db;
  $sql = "SELECT s.id,s.qty,s.price,s.date,p.name
          FROM sales s
          LEFT JOIN products p ON s.product_id=p.id
          ORDER BY s.date DESC LIMIT ".(int)$limit;
  return find_by_sql($sql);
}

/*--------------------------------------------------------------*/
function dailySales($year,$month){
  $sql = "SELECT s.qty,
          DATE_FORMAT(s.date,'%Y-%m-%e') AS date,p.name,
          SUM(p.sale_price*s.qty) AS total_saleing_price
          FROM sales s
          LEFT JOIN products p ON s.product_id=p.id
          WHERE DATE_FORMAT(s.date,'%Y-%m')='{$year}-{$month}'
          GROUP BY DATE_FORMAT(s.date,'%e'),s.product_id";
  return find_by_sql($sql);
}

/*--------------------------------------------------------------*/
function monthlySales($year){
  $sql = "SELECT s.qty,
          DATE_FORMAT(s.date,'%Y-%m') AS date,p.name,
          SUM(p.sale_price*s.qty) AS total_saleing_price
          FROM sales s
          LEFT JOIN products p ON s.product_id=p.id
          WHERE DATE_FORMAT(s.date,'%Y')='{$year}'
          GROUP BY DATE_FORMAT(s.date,'%c'),s.product_id
          ORDER BY DATE_FORMAT(s.date,'%c') ASC";
  return find_by_sql($sql);
}
?>
