<?php
include 'database.php'; //データベース情報

// HTML特殊文字をエスケープする関数
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

//時刻をマイクロ秒単位で取得する関数
function getUnixTimeMillSecond(){
    $arrTime = explode('.', microtime(true)); //microtimeを.で分割
    return date('Y-m-d H:i:s', $arrTime[0]) . '.' .$arrTime[1]; //日時＋ミリ秒
}


try {

	$pdo = new PDO($dsn, $user, $password) ;

	try {

		//アップロードファイルの例外処理
		switch ($_FILES['upfile']['error']) {
		    case UPLOAD_ERR_OK: // OK
		    case UPLOAD_ERR_NO_FILE: // ファイル未選択
		        break;
			case UPLOAD_ERR_INI_SIZE:  // php.ini定義の最大サイズ超過
		    case UPLOAD_ERR_FORM_SIZE: // フォーム定義の最大サイズ超過 (設定した場合のみ)
		        throw new RuntimeException('ファイルサイズが大きすぎます');
		    default:
		        throw new RuntimeException('その他のエラーが発生しました');
		}

		if (!empty($_POST["submit"])) { //送信ボタンが押されたら

			if (empty($_POST["title"])) {
				throw new RuntimeException('タイトルを入力してください');
			} elseif ($_POST["label"] === "none") {
				throw new RuntimeException('ラベルを選択してください');
			}

			if ($_FILES['upfile']['error'] !== 4) { //UPLOAD_ERR_NO_FILE(4)

				if (!isset($_FILES['upfile']['error']) || !is_int($_FILES['upfile']['error'])) {
					throw new RuntimeException('パラメータが不正です');
				} else {

					$rawData = file_get_contents($_FILES["upfile"]["tmp_name"]); //バイナリデータを取得
					$date = getdate(); //時刻を取得
					$mime = $_FILES["upfile"]["type"] ; //MIMEタイプを判定

					// 拡張子を決定
					switch ($mime) {
						case "image/jpeg":
							$extension = ".jpeg";
							break;
						case "image/png":
							$extension = ".png";
							break;
						case "image/gif":
							$extension = ".gif";
							break;
						case "video/mp4":
							$extension = ".mp4";
							break;
						default:
							throw new RuntimeException("非対応ファイルです");
					}

					// バイナリデータと時刻を合わせてハッシュ化
					$hashname = hash("sha256", $rawData.$date["year"].$date["mon"].$date["mday"].$date["hours"].$date["minutes"].$date["seconds"]);
					$filename = $hashname.$extension ;

					//ファイルを特定のフォルダへ移動
					if (move_uploaded_file($_FILES["upfile"]["tmp_name"], "files/" . $filename)) {
						if ("$mime" === "video/mp4") { //動画のとき
							$format = '<video src="/files/%s" controls autoplay></video>' ;
						} else { //画像のとき
							$format = '<img src="/files/%s">' ;
						}
					} else {
				    	throw new RuntimeException("ファイルをアップロードできませんでした");
					}

				}

			}

            //変数に入れ直し
            $title = $_POST["title"];
			$text = $_POST["text"];

            //時刻を取得(マイクロ秒まで)
            $datetime = getUnixTimeMillSecond();

            //投稿ごとのファイルのための下準備
            $pageUrl = md5($title . $datetime); //タイトルと時刻でハッシュ
            $postFilename = $pageUrl . ".php"; //新しい投稿のファイル名
            $content = file_get_contents("template.php"); //テームレートの読み込み
            $title = h($title); //HTML特殊文字をエスケープ
            $text = h($text); //同上
            $text = nl2br($text); //改行文字を変換

			//データベースへの書き込み
			$sql = $pdo -> prepare("INSERT INTO post(user, title, text, filename, thumbnail, datetime, label, rating) VALUES (:user, :title, :text, :filename, :thumbnail, :datetime, :label, :rating)") ;
			$sql -> bindValue(':user', $_POST["user"], PDO::PARAM_STR) ;
			$sql -> bindValue(':title', $_POST["title"], PDO::PARAM_STR) ;
			$sql -> bindValue(':text', $_POST["text"], PDO::PARAM_STR) ;
			$sql -> bindValue(':filename', $filename, PDO::PARAM_STR) ;
			$sql -> bindValue(':thumbnail', "disabled", PDO::PARAM_STR) ; //現在未実装
			$sql -> bindValue(':datetime', $datetime, PDO::PARAM_STR) ;
			$sql -> bindValue(':label', $_POST["label"], PDO::PARAM_STR) ;
			$sql -> bindValue(':rating', "0", PDO::PARAM_INT) ;
			$sql -> execute() ;

            //データベースから新しい投稿のIDを取得(時刻基準)
            $sql = "SELECT * FROM post WHERE datetime = '$datetime'";
            $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
            $id = $row["id"];

            //各種内容の置換
            $content = str_replace("<%pageTitle>", $title, $content);
            $content = str_replace("<%pageText>", $text, $content);
            $content = str_replace("strFormat", $format, $content);
            $content = str_replace("strFilename", $filename, $content);
            $content = str_replace("strPageID", $id, $content);

            //ファイル生成 & 書き込み
			chdir("/home/tt-576.99sv-coco.com/public_html/posts"); //ディレクトリ移動
			$handle = fopen($postFilename, 'w');
			fwrite($handle, $content);
			fclose($handle);
			chdir("/home/tt-576.99sv-coco.com/public_html"); //元のディレクトリ

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
<title>新規投稿</title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
</head>
<body>

<p>
	<strong>新規投稿</strong>
	<div><font color="red"><?php echo h($errorMessage); ?></font></div>
	<form action="" method="post" enctype="multipart/form-data">
		ユーザー名(自動化予定): <input type="text" name="user">
		<br>
		タイトル(必須): <input type="text" name="title" value="<?php if (!empty($_POST["title"])) {echo h($_POST["title"]);} ?>">
		<br>
		コメント:
		<br>
			<textarea name="text" cols="40" rows="4"><?php if (!empty($_POST["text"])) { echo h($_POST["text"]);} ?></textarea>
		<br>
		<br>
		<!--- ファイルサイズ制限、10MB --->
		<input type="hidden" name="MAX_FILE_SIZE" value="10485760">
		ファイル: <input type="file" name="upfile" size="30">
		<br>
		※条件: 10MB以内, JPEG, PNG, GIF, MP4のいずれか
        <br>
		ラベル(必須):
			<select name="label">
				<option value="none" selected></option>
				<option value="釣りタイトル">釣りタイトル</option>
				<option value="議論">議論</option>
				<option value="おもしろ">おもしろ</option>
				<option value="質問">質問</option>
			</select>
        <br>
        <input type="submit" name ="submit" value="送信">
	</form>
</p>

<h3>投稿一覧</h3>
<?php
$sql = 'SELECT * FROM post ORDER BY id ASC' ;
$results = $pdo -> query($sql) ;
foreach ($results as $row) {

	$pageUrl = md5($row['title'] . $row['datetime']);
	echo '<a href="/posts/'.$pageUrl.'.php">' ;
	echo "投稿番号 " . $row['id'];
	echo "「" . h($row['title']) . "」";
	//echo $row['text'].',' ;
	//echo $row['filename'].',' ;
	//echo $row['thumbnail'].',' ;
    echo "<" . $row['label'] . "> ";
    echo "評価: " . $row['rating'] . "ポイント " ;
	echo $row['datetime'];
    echo " By " . $row['user'];
	echo '</a>' ;
	echo '<br>' ;

} ;
?>

</body>
</html>
