<?php
namespace TymFrontiers;
require_once ".appinit.php";
require_once APP_BASE_INC;
\header("Content-Type: application/json");

$post = \json_decode( \file_get_contents('php://input'), true); // json data
$post = !empty($post) ? $post : (
  !empty($_POST) ? $_POST : (
    !empty($_GET) ? $_GET : []
    )
);
$gen = new Generic;
$params = $gen->requestParam([
  "user" => ["user","username",3,12],
  "group" => ["group","username",2,28,[],"MIXED",['-','.','_']],
  "format" => ["format","option",["json","text","xml","html"]],
  "access_rank" => ["access_rank","int"]
],$post,["group"]);
if (!$params || !empty($gen->errors)) {
  $errors = (new InstanceError($gen,true))->get("requestParam",true);
  echo \json_encode([
    "status" => "3." . \count($errors),
    "errors" => $errors,
    "message" => "Request halted"
  ]);
  exit;
}
$rank = empty($params['access_rank']) ? (
  $session instanceof Session ? $session->access_rank : 0
) : $params['access_rank'];
$user = empty($params['user']) ? (
  $session instanceof Session ? $session->name : "NA"
) : $params['user'];

$navs = [];
$query = "SELECT wp.name, wp.path, wp.access_rank, wp.access_rank_strict, wp.title,
                 wp.icon, wp.description,
                 dm.path AS domain_path
           FROM :db:.:tbl: AS wp
           LEFT JOIN :db:.work_domain AS dm ON dm.name = wp.domain
           WHERE wp.nav_visible = TRUE
           AND wp.domain='{$db->escapeValue($params['group'])}'
           AND  (
             (wp.access_rank_strict = TRUE AND wp.access_rank = {$rank}) OR (
               wp.access_rank <= {$rank}
             )
           )
           AND (
             wp.name IN(
               SELECT path_name
               FROM :db:.path_access
               WHERE user='{$db->escapeValue($user)}'
             ) OR (
               (
                 SELECT COUNT(*)
                 FROM :db:.path_access
                 WHERE path_name = (
                    SELECT `name`
                    FROM :db:.work_path
                    WHERE `domain` = '{$db->escapeValue($params['group'])}'
                    AND `path` = '/'
                    LIMIT 1
                 )
                 AND user = '{$db->escapeValue($user)}'
               ) > 0
             )
           )
           ORDER BY wp.`sort`, wp.title ASC";
$found_nav = (new \TymFrontiers\MultiForm(MYSQL_ADMIN_DB,'work_path','name'))->findBySql($query);
// echo $database->last_query;
// exit;
if ($found_nav) {
  foreach ($found_nav as $nav) {
    $navs[] = [
      "access_rank" => (int)$nav->access_rank,
      "access_rank_strict" => (int)$nav->access_rank_strict,
      "title" => $nav->title,
      "path" => $nav->path,
      "link" =>  $nav->domain_path . $nav->path,
      "newtab" =>  empty($nav->newtab) ? false : (bool)$nav->newtab,
      "onclick" => (!empty($nav->onclick) ? $nav->onclick : ''),
      "icon" => (!empty($nav->icon) ? \html_entity_decode($nav->icon) : ""),
      "name" => $nav->domain_path . $nav->path,
      "classname" => (!empty($nav->classname) ? $nav->classname : '')
    ];
  }
} else {
  echo json_encode([
    "status" => "0.1",
    "message" => "Request completed",
    "errors" => ["No result found."],
    "navlist" => []
  ]);
  exit;
}

echo \json_encode([
  "status" => "0.0",
  "errors" => [],
  "message" => "Request completed",
  "navlist" => $navs
]);
exit;
