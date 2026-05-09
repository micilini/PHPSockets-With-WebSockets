<!DOCTYPE html> 
<html> 
<head> 
<title>WebSockets com HTML5</title> 
<meta charset="UTF-8"> <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
<link rel="stylesheet" href="style.css"> 
</head> 
<body> 
<div id="page-wrapper"> 

<h1>Exemplo de utilização de WebSockets</h1>
<div id="status">Conectando a aplicação...</div> 
<ul id="mensagens"></ul> <form id="mensagem-formulario" action="#" method="post"> 
<textarea id="mensagem" placeholder="Escreva a sua mensagem aqui!" required></textarea> 
<button type="submit">Enviar mensagem</button> 
<button type="button" id="close">Fechar conexão</button> 
 
 </form> 
 
 </div> 
 
 <script src="aplicacao.js"></script> 
 
 </body> 
 </html>
