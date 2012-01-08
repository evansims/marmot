<?php

	// Identity receipt emails will be sent as.
	$receiptFrom = 'Marmot <marmot@yourserver.com>';

	// If your server has SSL support, turn this on.
	$supportHTTPS = false;

	// Internal variables. Don't modify.
	$domain = '';
	$salt = '';
	$email = '';
	$length = 10;
	$output = '';

	if(isset($_POST['d']) && strlen($_POST['d']) &&
	isset($_POST['s']) && strlen($_POST['s'])) {
		$domain = trim($_POST['d']);
		$salt = trim($_POST['s']);
		$email = trim($_POST['e']);
		$length = (int)trim($_POST['l']);
	} elseif(isset($_GET['d']) && strlen($_GET['d']) &&
	isset($_GET['s']) && strlen($_GET['s'])) {
		$domain = trim($_GET['d']);
		$salt = trim($_GET['s']);
		$email = trim($_GET['e']);
		$length = (int)trim($_GET['l']);
	}

	if($domain && $salt && $length) {
		if(strlen($salt) < 32) {
			$salt = md5(preg_replace('/[^a-zA-Z0-9\s]/', '', $salt));
		}

		$domain = strtolower($domain);
		$host = $domain;

		if(substr($host, 0, 7) != 'http://' && substr($host, 0, 8) != 'https://') {
			$host = 'http://' . $host;
		} elseif(substr($host, 0, 8) == 'https://') {
			$host = 'http://' . substr($host, 8);
		}

		$host = @parse_url($host);

		if(sizeof($host) >= 2) {
			$host = explode('.', $host['host']);
			if(sizeof($host) >= 2) {
				$host = $host[sizeof($host)-2] . '.' . $host[sizeof($host)-1];
			} else {
				$host = '';
			}
		} else {
			$host = '';
		}

		if(!strlen($host)) {
			$output = '<span class="error">Invalid Domain</span>';
		} else {
			$password = cleansePassword(md5("{$salt}/{$host}/{$length}"), $length);

			// Don't allow consecutive characters.
			$i = 0;
			while(hasRepeatingCharacter($password)) {
				$i++;
				$password = cleansePassword(md5($password . $i), $length);
			}

			if(sizeof($email) && strpos($email, '@')) {
				@mail($email, '[Marmot] Generated password for ' . strtoupper($host), "New password: {$password}", "From: {$receiptFrom}");
			}

			if(isset($_GET['api']) || isset($_POST['api'])) {
				returnJS($host, $password);
			} else {
				$output = $password;
			}
		}
	}

	function cleansePassword($password, $length) {
		if(strlen($password) > $length) {
			$password = substr($password, 0, $length);
		}

		// Always start off a password with a non-numeric character.
		if(is_numeric($password[0])) {
			$password[0] = chr(65 + $password[0]);
		}

		// Capitalize certain characters.
		$a = 1;
		for($i = 0; $i < strlen($password); $i++) {
			if(!is_numeric($password[$i])) {
				$a++;
				if($a == 2) {
					$password[$i] = strtoupper($password[$i]);
					$a = 0;
				}
			}
		}

		return $password;
	}

	function hasRepeatingCharacter($password) {
		$last = $password[0];
		for($i = 1; $i < strlen($password); $i++) {
			if($password[$i] == $last) {
				return true;
			}
			$last = $password[$i];
		}
		return false;
	}

	function returnJS($domain, $pass) {
		header('Content-Type: text/javascript');
		$funcname = 'marmot' . md5(microtime(false));
		echo "function {$funcname}(){e=document.activeElement;if(e && e.tagName=='INPUT'){e.value='{$pass}'}else{prompt('Password for {$domain}','{$pass}')}}{$funcname}();";
		exit;
	}

?><!DOCTYPE html>
<html>

	<head>
		<title>Marmot Password Manager</title>

		<style type="text/css">

			input.text {
				width: 200px;
			}

			span.error {
				color: red;
			}

		</style>
	</head>

	<body>

		<h1>Marmot Password Manager</h1>

		<form method="post">
			<table style="border: 0;">
				<tr>
					<td style="padding-right: 10px"><label for="domain">Domain:</label></td>
					<td><input class="text" type="text" name="d" id="domain" placeholder="http://www.google.com" autofocus value="<?php echo($host); ?>" /></td>
				</tr>
				<tr>
					<td style="padding-right: 10px"><label for="salt">Salt:</label></td>
					<td><input class="text" type="password" name="s" id="salt" placeholder="Password" value="<?php echo($salt); ?>" /></td>
				</tr>
				<tr>
					<td style="padding-right: 10px"><label for="length">Length:</label></td>
					<td><select name="l" id="length"><?php
						for($i = 1; $i <= 32; $i++) {
							if($i == $length) {
								echo "<option selected=\"selected\">{$i}</option>";
							} else {
								echo "<option>{$i}</option>";
							}
						}
					?></select></td>
				</tr>
				<tr>
					<td style="padding-right: 10px"><label for="email">Email:</label></td>
					<td><input class="text" type="email" name="e" id="email" placeholder="Sends a receipt for safe keeping." value="<?php echo($email); ?>" /></td>
				</tr>
				<tr>
					<td>&nbsp;</td>
					<td><input type="submit" value="Generate" /></td>
				</tr>
			</table>
		</form>

		<?php
			if($output):
				echo "<hr /><h1>{$output}</h1>";
				if($host):
		?>
			<p><small>This password will work on any *.<?php echo $host; ?> site.</small></p>
			<hr />
			<p><a href="<?php
				$protocol = "'http";
				if(isset($_SERVER['HTTPS']) || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) $protocol = "'https";
				if($supportHTTPS) $protocol = "l.protocol+'";

				$bkmlt = $_SERVER["SERVER_NAME"]  . $_SERVER["SCRIPT_NAME"] . '?api&amp;s=' . $salt . '&amp;l=' . $length . '&amp;e=' . urlencode($email) . '&amp;d=';
				echo "javascript:(function(){var d=document,l=d.location,e=d.activeElement,b=d.body,z=d.createElement('scr'+'ipt');z.setAttribute('src',({$protocol}://{$bkmlt}'+encodeURIComponent(l.href)));b.appendChild(z)})();";
			?>" onclick="return false">&#8984; Marmot</a> &mdash; Drag this bookmarklet to your bar to easily generate passwords on other websites.</p>
		<?php endif; endif; ?>

		<a href="https://github.com/evansims/marmot"><img style="position: absolute; top: 0; right: 0; border: 0;" src="https://a248.e.akamai.net/assets.github.com/img/30f550e0d38ceb6ef5b81500c64d970b7fb0f028/687474703a2f2f73332e616d617a6f6e6177732e636f6d2f6769746875622f726962626f6e732f666f726b6d655f72696768745f6f72616e67655f6666373630302e706e67" alt="Fork me on GitHub"></a>

	</body>

</html>
