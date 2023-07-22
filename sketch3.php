<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$dbh = new PDO('sqlite::memory:');
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$dbh->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
$dbh->exec(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'schema3.sql'));

function createShape() {
	global $dbh;

	return $dbh->query("INSERT INTO shape DEFAULT VALUES RETURNING ID")->fetchColumn();
}

function createAnchor($shapeId, $relX = 0.5, $relY = 0.5) {
	global $dbh;

	$stmt = $dbh->prepare("INSERT INTO shape_anchor (shape_id, relative_x, relative_y) VALUES (:shape_id, :relative_x, :relative_y) RETURNING ID");
	$stmt->execute([
		':shape_id' => $shapeId,
		':relative_x' => $relX,
		':relative_y' => $relY,
	]);

	return $stmt->fetchColumn();
}


function createNode($x,$y, $shapeId) {
	global $dbh;
	$elementId = $dbh->query("INSERT INTO element DEFAULT VALUES RETURNING ID")->fetchColumn();

	$stmt = $dbh->prepare("INSERT INTO node
	(element_id, shape_id, position_x, position_y) VALUES 
	(:element_id, :shape_id, :position_x, :position_y)
	 RETURNING ID");
	$stmt->execute([
		'element_id' => $elementId,
		'shape_id' => $shapeId,
		'position_x' => $x,
		'position_y' => $y,
	]);

	return [$stmt->fetchColumn(), $elementId];
}

function createEdge($sourceNode, $sourceAnchor, $targetNode, $targetAnchor, $elementId = null) {
	global $dbh, $shapeId1;
	$dbh->beginTransaction();
	$elementId = $elementId != null ? $elementId : $dbh->query("INSERT INTO element DEFAULT VALUES RETURNING ID")->fetchColumn();
	$stmt = $dbh->prepare("INSERT INTO edge
	(element_id, 
		source_node_id,
		source_shape_id,
		source_anchor_id,
		target_node_id,
		target_shape_id,
		target_anchor_id) 
			SELECT 
			:element_id, source_node.id, source_anchor.shape_id, source_anchor.id,
			target_node.id, target_anchor.shape_id, target_anchor.id 
			FROM 
			node source_node, 
			node target_node
			INNER JOIN shape_anchor source_anchor
			ON source_anchor.shape_id = source_node.shape_id
			INNER JOIN shape_anchor target_anchor
			ON target_anchor.shape_id = target_node.shape_id
			WHERE (source_node.id, target_node.id) = (:source_node_id, :target_node_id)
			AND (source_anchor.id, target_anchor.id) = (:source_anchor_id, :target_anchor_id)
			LIMIT 1
	 RETURNING ID");

	$stmt->execute([
		':element_id' => $elementId,
		':source_node_id' => $sourceNode,
		':source_anchor_id' => $sourceAnchor,
		':target_node_id' => $targetNode,
		':target_anchor_id' => $targetAnchor,
	]);

	$edgeId = $stmt->fetchColumn();
	$stmt->closeCursor();
	if (!$edgeId) {
		$dbh->rollback();
		throw new Exception("Creating Edge failed");
	} else if ($dbh->inTransaction()) { 
	    $dbh->commit();
	}

	return [$edgeId, $elementId];
}

function createPathPoint($elementId, $x,$y) {
	global $dbh;

	$stmt = $dbh->prepare("INSERT INTO path_point(element_id, x,y, sort)
		VALUES(:element_id, :x, :y, 0)
	 RETURNING ID");
	$stmt->execute([
		'element_id' => $elementId,
		'x' => $x,
		'y' => $y,
	]);

	return [$stmt->fetchColumn(), $elementId];
}


function createText($elementId, $content = '', $x = 0, $y = 0) {
	global $dbh;

	$dbh->beginTransaction();

	$stmt = $dbh->prepare("INSERT INTO text
	(element_id, content, position_x, position_y) VALUES 
	(:element_id, :content, :position_x, :position_y)
	 RETURNING ID");
	$stmt->execute([
		'element_id' => $elementId,
		'content' => $content,
		'position_x' => $x,
		'position_y' => $y,
	]);

	$textId = $stmt->fetchColumn();
	$stmt->closeCursor();

	if (!$textId) {
		$dbh->rollback();
		throw new Exception("Creating Edge failed");
	} else if ($dbh->inTransaction()) { 
	    $dbh->commit();
	}

	return $textId;
}

function repair() {
	global $dbh;

	$dbh->exec("DELETE FROM element WHERE id IN (SELECT element_id FROM view_error_orphan_element)");
	$dbh->exec("DELETE FROM text WHERE id IN (SELECT text_id FROM view_error_empty_text)");
	$dbh->exec("DELETE FROM edge WHERE element_id IN (SELECT element_id FROM view_error_edge_node_conflict)");

}


$shapeId1 = createShape();
$shapeId2 = createShape();
$shapeId3 = createShape();

$anchor1 = createAnchor($shapeId1);
$anchor2 = createAnchor($shapeId1,0,0);
$anchor3 = createAnchor($shapeId1,0,1);
$anchor4 = createAnchor($shapeId1,1,0);
$anchor5 = createAnchor($shapeId1,1,1);

$n3 = createNode(0,-30, $shapeId1);
$n1 = createNode(20,40, $shapeId1);
$n2 = createNode(-50,80, $shapeId1);

$e1 = createEdge($n1[0], $anchor3, $n2[0], $anchor5);
$e2 = createEdge($n2[0], $anchor2, $n3[0], $anchor2);
createPathPoint($e2[1], -90, 0);
createPathPoint($e1[1], 10, 100);

$t1 = createText($n1[1], "foo",0,20);
$t2 = createText($n2[1], "foo",0,-10);
$t3 = createText($e2[1], "foo");

$t3 = createText($e2[1], "");
$dbh->query("INSERT INTO element DEFAULT VALUES RETURNING ID")->fetchColumn();
$e3 = createEdge($n2[0], $anchor2, $n3[0], $anchor2, $n2[1]);

$camId1 = $dbh->query("INSERT INTO ui_camera(center_x,center_y,zoom) VALUES (
	ROUND(200*RANDOM()/9223372036854775807),
	ROUND(200*RANDOM()/9223372036854775807),
	0.6) RETURNING ID")->fetchColumn();
$camId2 = $dbh->query("INSERT INTO ui_camera(center_x,center_y,zoom) VALUES (
	ROUND(200*RANDOM()/9223372036854775807),
	ROUND(200*RANDOM()/9223372036854775807),
	0.6) RETURNING ID")->fetchColumn();
$vpStmt = $dbh->prepare("INSERT INTO ui_viewport(width,height,ui_camera_id) VALUES (:w,:h,:camera_id)");
$vpStmt->execute([':camera_id' => $camId1, 'w' => 500, 'h'=>300]);
$vpStmt->execute([':camera_id' => $camId2, 'w' => 300, 'h'=>600]);



$errors = $dbh->query("SELECT * FROM view_error_report")->fetchAll();
repair();
$errorsAfterRepair = $dbh->query("SELECT * FROM view_error_report")->fetchAll();



?>

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title>SVG</title>
	<style>
		svg {
			border: 1px solid gray;
			display: block;
		}

		section {
			display: grid;
			align-content: center;
			justify-content: center;
		}
	</style>
</head>
<body>
	<section>
		<ul>
			<?php foreach ($errorsAfterRepair as $err): ?>
				<li><?php echo $err->error ?>
				Id: <?php echo $err->element_id ?></li>
			<?php endforeach ?>
		</ul>
		<pre><?php echo json_encode($errors, JSON_PRETTY_PRINT) ?></pre>

		<?php foreach ([1,2] as $vp): ?>
			<?php 
			$nodes = $dbh->query("SELECT * FROM view_bounded_node_in_viewport WHERE viewport_id=$vp")->fetchAll();
			$anchors = $dbh->query("SELECT * FROM view_bounded_anchor_in_viewport WHERE viewport_id=$vp")->fetchAll();
			$texts = $dbh->query("SELECT * FROM view_bounded_text_in_viewport WHERE viewport_id=$vp")->fetchAll();
			$edges = $dbh->query("SELECT * FROM view_bounded_edge_in_viewport WHERE viewport_id=$vp")->fetchAll();

			$viewport = $dbh->query("SELECT * FROM view_bounded_ui_viewport WHERE id=$vp")->fetch();
		 ?>

		 <svg viewBox="<?php echo $viewport->min_x ?> <?php echo $viewport->min_y ?> <?php echo $viewport->width ?> <?php echo $viewport->height ?>" width="<?php echo $viewport->width ?>" height="<?php echo $viewport->height ?>">
			<?php foreach ($nodes as $node): ?>
				<rect 
				x="<?php echo $node->min_x ?>" 
				y="<?php echo $node->min_y ?>"
				width="<?php echo $node->width ?>"
				height="<?php echo $node->height ?>"></rect>
			<?php endforeach ?>

			<?php foreach ($anchors as $anchor): ?>
				<rect 
				fill="orange"
				x="<?php echo $anchor->min_x ?>" 
				y="<?php echo $anchor->min_y ?>"
				width="<?php echo $anchor->width ?>"
				height="<?php echo $anchor->height ?>"></rect>
			<?php endforeach ?>


			<?php foreach ($edges as $edge): ?>
			<polyline fill="none" stroke-width="3" stroke="blue" points="
			<?php echo $edge->source_x ?>
			<?php echo $edge->source_y ?>
			<?php foreach (json_decode($edge->points) as $p): ?>
				
			<?php echo $p->x ?>
			<?php echo $p->y ?>
			<?php endforeach ?>
			<?php echo $edge->target_x ?>
			<?php echo $edge->target_y ?>
			"></polyline>
			<?php endforeach ?>

			<?php foreach ($texts as $text): ?>
				<text 
				x="<?php echo $text->center_x ?>" 
				y="<?php echo $text->center_y ?>"
				text-anchor="middle"
				dominant-baseline="middle"
				fill="white"
				stroke="gray"
				>
				<?php echo $text->content ?>
			</text>
			<?php endforeach ?>
		</svg>
		<details>
			<summary>Data</summary>
			

		<pre><?php echo json_encode($viewport, JSON_PRETTY_PRINT) ?></pre>
		<pre><?php echo json_encode($nodes, JSON_PRETTY_PRINT) ?></pre>
		<pre><?php echo json_encode($anchors, JSON_PRETTY_PRINT) ?></pre>
		<pre><?php echo json_encode($texts, JSON_PRETTY_PRINT) ?></pre>
		<pre><?php echo json_encode($edges, JSON_PRETTY_PRINT) ?></pre>
		</details>
		<?php endforeach ?>
		
	</section>
</body>
</html>