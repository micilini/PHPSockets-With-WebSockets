# PHPSockets With WebSockets

Here we will see how to create a websocket server working with a php sockets.

The file is meant to be understood in a simple and effective way.

<h3>EasyChat or MediumChat?</h3>

You see that has 2 folders: EasyChat and MediumChat, I will explain the difference below:

<b>EasyChat -></b> is a chat created to beginners, since it has a simple code to understand and easy to use.

<b>MediumChat -></b> is a chat created for people with intermediate level of knowledge of PHP, this is separated by classes and complex functions.

<h3>How to setup?</h3>

Testing in your local apache all ports are avaiable (I hope so).

To configure host in WebSockets open aplicacao.js and find the <code>var host = 'ws://localhost:8080';</code> in my case is listening in localhost in port 8080.

To configure host in PHP Sockets open server.php and find the <code>$adr = "localhost"; $port = 8080;</code> in my case is running in localhost in port 8080.

<blockquote>If you are using the <b>MediumChat</b>, you need to open server.php file and go to the last line code and find <code>$Server->wsStartServer('127.0.0.1', 8080);</code> 127.0.0.1 is your localhost and 8080 is the port</blockquote>

(The port and address need to be the same - If the websockets is in port 6570, php sockets need to be in port 6570)


<h3>How to Test It?</h3>

<ul>1. I'm using WampServer (Version: 2.4.9, PHP: 5.5.12)</ul>
<ul>2. You need to download the files from my github and put inside your apache, in my case, inside www folder. (C://wamp/www/Sockets)</ul>
<ul>3. Great now you have to open in your browser the index.php file, when you do this you'll receive this message in red: "Disconectado do WebSocket.", this is because you don´t setup the server yet.</ul>
<ul>4. To setup the server open the file server.php in your browser, and you´ll see that page in a infinite loop (loading....loading....loading in browser), cool! The socket is running!</ul>
<ul>5. Now go to index.php file and if you receive a message in color green, you are connected with the server :D</ul>


<h3>Common questions</h3>

<ul>This chat is private? <b>No, it is a global chat</b>.</ul>
<ul>When private version of this chat will come out? <b>It is still a puzzle to me, but will be available soon.</b>.</ul>
<ul>This chat use node.js or socket.io? <b>No, I use php and javascript, and is working as well as they</b></ul>
