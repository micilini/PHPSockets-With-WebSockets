window.onload = function() { 

var form = document.getElementById('mensagem-formulario'); 
var mensagemTexto = document.getElementById('mensagem');
var listaMensagem = document.getElementById('mensagens');
var socketStatus = document.getElementById('status'); 
var btnFechar = document.getElementById('close'); 

// Criando uma nova conexão WebSocket.

var socket = new WebSocket('ws://localhost:8080');

socket.onopen = function(event) { 

socketStatus.innerHTML = 'Connect with ' + event.currentTarget.URL; socketStatus.className = 'open'; 

};

socket.onerror = function(error) { 

console.log('Error: ' + error); 

socketStatus.innerHTML = 'Error: ' + error;

};

form.onsubmit = function(e) { e.preventDefault(); 
var mensagem = mensagemTexto.value; 
socket.send(mensagem); 
listaMensagem.innerHTML += '<li class="envia"><span>Enviado:</span>' + mensagem + '</li>'; 
mensagemTexto.value = ''; return false; 

};

socket.onmessage = function(event) { 
var mensagem = event.data; listaMensagem.innerHTML += '<li class="recebida"><span>Recebida:</span>' + mensagem + '</li>'; 
};

socket.onclose = function(event) { 

socketStatus.innerHTML = 'Disconectado do WebSocket.'; socketStatus.className = 'closed'; 

};

btnFechar.onclick = function(e) { 
e.preventDefault(); 
socket.close(); return false; 
};

};

