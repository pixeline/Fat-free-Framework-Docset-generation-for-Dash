<?php
/*
	Note to self: should read this readme https://github.com/Kapeli/Dash-User-Contributions
	
*/
$lang = 'en';
$ver  = '3.6';

exec("rm -rf fat-free-framework.docset");
exec("mkdir -p fat-free-framework.docset/Contents/Resources/Documents/");
//exec("wget -rkl1 https://fatfreeframework.com/3.6/user-guide");
exec("cp -a " . __DIR__ . "/html/. " . __DIR__ . "/fat-free-framework.docset/Contents/Resources/Documents/");


file_put_contents(__DIR__ . "/fat-free-framework.docset/Contents/Info.plist", <<<ENDE
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
	<key>CFBundleIdentifier</key>
	<string>fat-free-framework-{$lang}</string>
	<key>CFBundleName</key>
	<string>Fat-Free Framework (f3) {$ver}-{$lang}</string>
	<key>DocSetPlatformFamily</key>
	<string>fat-free-framework</string>
	<key>isDashDocset</key>
	<true/>
	<key>dashIndexFilePath</key>
	<string>index.html</string>
</dict>
</plist>
ENDE
);
copy(__DIR__ . "/icon.png", __DIR__ . "/fat-free-framework.docset/icon.png");

$dom = new DomDocument;
@$dom->loadHTMLFile(__DIR__ . "/fat-free-framework.docset/Contents/Resources/Documents/index.html");

$db = new sqlite3(__DIR__ . "/fat-free-framework.docset/Contents/Resources/docSet.dsidx");
$db->query("CREATE TABLE searchIndex(id INTEGER PRIMARY KEY, name TEXT, type TEXT, path TEXT)");
$db->query("CREATE UNIQUE INDEX anchor ON searchIndex (name, type, path)");

$html = file_get_contents(__DIR__ . "/fat-free-framework.docset/Contents/Resources/Documents/index.html");
$p = strpos($html, '<nav');
if ($p !== false) {
	$q = strpos($html, '</nav');
	$html = substr($html, 0, $p) . substr($html, $q + 6);

	file_put_contents(__DIR__ . "/fat-free-framework.docset/Contents/Resources/Documents/index.html", $html);
}

// add links from the table of contents
$links = $edited = array();
foreach ($dom->getElementsByTagName("a") as $a) {
	$href = $a->getAttribute("href");
	$str  = substr($href, 0, 6);
	if ($str[0] == '.') continue;
	if ($str == 'https:' || !strncmp($str, 'http:', 5)) continue;

	$file = preg_replace("/#.*$/", "", $href);
	if (!isset($edited[$file]) && $file != "index.html") {
		$html = file_get_contents(__DIR__ . "/fat-free-framework.docset/Contents/Resources/Documents/" . $file);
		$p = strpos($html, '<div class="col-md-4 col-lg-3">');
		if ($p !== false) {
			$q = strpos($html, '<div class="col-md-8 col-lg-9">');
			$html = substr($html, 0, $p) . "<div style='padding: 1.5em'>" . substr($html, $q + 31);

			$p = strpos($html, '<nav');
			$q = strpos($html, '</nav');
			$html = substr($html, 0, $p) . substr($html, $q + 6);

			file_put_contents(__DIR__ . "/fat-free-framework.docset/Contents/Resources/Documents/" . $file, $html);
		}
		$edited[$file] = true;
	}

	$name = trim(preg_replace("#\s+#", " ", preg_replace("#^[A-Z0-9–]+\.#", "", $a->nodeValue)));
	if (empty($name)) continue;

	$class = "Guide";
	if (substr($href, 0, 30) == "writing-tests-for-fat-free-framework.html" && strpos($name, "(") !== false) $class = "Function";
	$links[$name] = true;
	$db->query("INSERT OR IGNORE INTO searchIndex(name, type, path) VALUES (\"$name\",\"$class\",\"$href\")");
}

// now go through some of the files to add pointers to inline documentation
foreach (array("appendixes.assertions", "appendixes.annotations", "incomplete-and-skipped-tests", "test-doubles", "writing-tests-for-fat-free-framework") as $file) {
	$search = $replace = array();
	@$dom->loadHTMLFile(__DIR__ . "/fat-free-framework.docset/Contents/Resources/Documents/$file.html");
	foreach ($dom->getElementsByTagName("td") as $td) {
		if (!$td->firstChild) continue;
		if (strtolower($td->firstChild->nodeName) != "code") continue;
		$name = $td->firstChild->nodeValue;
		if (!preg_match("#^([a-z_]+ )?([a-z0-9_]+\()#i", $name, $m)) continue;

		$name = isset($m[2]) ? $m[2] : $m[1];
		$anchor = preg_replace("#[^a-z]#i", "", $name);
		$href = $file .".html#" . $anchor;

		$search[] = '<td align="left"><code class="literal">' . $m[0];
		$replace[] = '<td align="left"><code class="literal" style="white-space: normal" id="' . $anchor . '">' . $m[0];

		$name .= ")";
		// echo $name, " -> ", $href, "\n";

		if (isset($links[$name])) continue;
		$db->query("INSERT OR IGNORE INTO searchIndex(name, type, path) VALUES (\"$name\",\"Function\",\"$href\")");
	}
	$html = file_get_contents(__DIR__ . "/fat-free-framework.docset/Contents/Resources/Documents/$file.html");
	$html = str_replace($search, $replace, $html);
	file_put_contents(__DIR__ . "/fat-free-framework.docset/Contents/Resources/Documents/$file.html", $html);
}
