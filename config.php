<?php
$config['Host']= 'smtp.office365.com';
$config['SMTPAuth']= true;
$config['Port']=587;
$config['Username']= 'appdev@gust.edu.kw';
$config['Password']= 'appdev01';
$config['sendFrom']='appdev@gust.edu.kw';

$config['email'] = array("abdalla.a@gust.edu.kw" , "sobeih.m@gust.edu.kw");
$config['from'] = 'appdev@gust.edu.kw';

//$config['exclude'] = './';
$config['os-mail'] = false;
//$config['recursive'] = false;

$config['path'] = '/var/www';
$config['default-paths']=array(''=>'alone',"sites/default/"=>'alone',"sites/all/themes/"=>'recursive',"themes/bartik"=>'recursive');
$config['ignore-paths'] =array('.','..','html','.cgi');
?>
