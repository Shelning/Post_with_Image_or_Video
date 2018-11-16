<?php
chdir("/home/tt-576.99sv-coco.com/public_html"); //ディレクトリ移動
include 'database.php'; //データベース情報
// chdir("/home/tt-576.99sv-coco.com/public_html/posts"); //元のディレクトリ

// HTML特殊文字をエスケープする関数を定義
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

//このページのURLを置換してもらう
$pageID = "strPageID";

try {

    $pdo = new PDO($dsn, $user, $password) ;

    try {

        if (!empty($_POST["submit"])) { //送信ボタンが押されたら

			if (empty($_POST["text"])) {
				throw new RuntimeException('コメントを入力してください');
			} elseif (empty($_POST["rating"])) {
				throw new RuntimeException('評価をしてください');
			}

            //コメントをデータベースに保存
            $sql = $pdo -> prepare("INSERT INTO comment(pageID, user, text, datetime) VALUES (:pageID, :user, :text, :datetime)") ;
            $datetime = new DateTime() ;
            $datetime = $datetime->format('Y-m-d H:i:s');
            $sql -> bindValue(':pageID', $pageID, PDO::PARAM_INT) ; //どの投稿(ID)に対するコメントなのか
            $sql -> bindValue(':user', $_POST["user"], PDO::PARAM_STR) ;
            $sql -> bindValue(':text', $_POST["text"], PDO::PARAM_STR) ;
            $sql -> bindValue(':datetime', $datetime, PDO::PARAM_STR) ;
            $sql -> execute() ;

            //投稿に対する評価値を更新
            $rating = $_POST["rating"];
            $sql = "update post set rating=rating+'$rating' where id='$pageID'";
            $result = $pdo->query($sql);

        }

    } catch (RuntimeException $e) {
		$errorMessage = $e->getMessage();
	}

} catch (PDOException $e) { //$eに例外の情報が格納される
	exit('データベースに接続できませんでした。' . $e->getMessage()) ; //$e->getMessage()で格納されたエラーメッセージを表示
}
?>


<!DOCTYPE html>
<html>
<head>
<title><%pageTitle></title>
<meta charset="UTF-8">
</head>
<body>

<h1><%pageTitle></h1>

<h2>コメント</h2>
<%pageText>
<br>
<br>
<?php //幅・高さを整える必要がある
$format = 'strFormat';
$filename = "strFilename";
//sprintf(フォーマットしたいもの, 1つ目の%sなどに入れるもの, 2つ目, etc.)
echo sprintf($format, $filename);
?>
<br>
<br>
<a href="../post.php">投稿一覧へ</a>  <a href="../post.php">新しく投稿する</a>
<p>
	<strong>コメントする</strong>
	<div><font color="red"><?php echo h($errorMessage); ?></font></div>
	<form action="" method="post" enctype="multipart/form-data">
		ユーザー名(自動化予定): <input type="text" name="user">
		<br>
		コメント:
		<br>
			<textarea name="text" cols="40" rows="4"><?php if (!empty($_POST["text"])) {echo h($_POST["text"]);} ?></textarea>
        <br>
		評価:
			<input type="radio" name="rating" value="1">高評価
			<input type="radio" name="rating" value="-1">低評価
		<br>
		<input type="submit" name ="submit" value="送信">
	</form>
</p>
<h3>コメント一覧</h3>
<?php
$sql = "SELECT COUNT(*) FROM comment WHERE pageID = '$pageID'" ;
$count = (int)$pdo->query($sql)->fetchColumn(); //コメント数をカウント

if ($count === 0) {
    echo "まだコメントがありません";
} else {
    $sql = "SELECT * FROM comment WHERE pageID = '$pageID' ORDER BY id DESC" ;
    $results = $pdo -> query($sql) ;
    foreach ($results as $row) {
        $comment = h($row['text']);
        $comment = nl2br($comment);
        // echo $row['id'].',' ;
        // echo $row['pageID'].',';
        echo h($row['user']) . ": ";
        echo "「" . $comment . "」" ;
        echo $row['datetime'];
        // echo $row['rating'].',' ; //今の所未実装
    	echo '<br>' ;
    }
}
?>

</body>
</html>
