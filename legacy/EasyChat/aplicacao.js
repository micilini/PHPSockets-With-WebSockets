window.onload = function() { // pega as referencias dos elementos da página. 

var form = document.getElementById('mensagem-formulario'); 
var mensagemTexto = document.getElementById('mensagem');
var listaMensagem = document.getElementById('mensagens');
var socketStatus = document.getElementById('status'); 
var btnFechar = document.getElementById('close'); 

// Criando uma nova conexão WebSocket.

var socket = new WebSocket('ws://localhost:8080');

socket.onopen = function(event) { 

socketStatus.innerHTML = 'Conectado com: ' + event.currentTarget.URL; socketStatus.className = 'open'; 

};

socket.onerror = function(error) { 

console.log('Erro do WebSocket: ' + error); 

socketStatus.innerHTML = 'Erro do WebSocket: ' + error;

};

form.onsubmit = function(e) { e.preventDefault(); // Recuperando a mensagem do textarea. 
var mensagem = mensagemTexto.value; // Enviando a mensagem através do WebSocket. 
socket.send(mensagem); // Adicionando a mensagem numa lista de mensagens enviadas. 
listaMensagem.innerHTML += '<li class="envia"><span>Enviado:</span>' + mensagem + '</li>'; // Limpando o campo da mensagem após o envio. 
mensagemTexto.value = ''; return false; 

};

socket.onmessage = function(event) { 
var mensagem = event.data; listaMensagem.innerHTML += '<li class="recebida"><span>Recebida:</span>' + mensagem + '</li>'; 
};

socket.onclose = function(event) { 

socketStatus.innerHTML = 'Disconectado do WebSocket.'; socketStatus.className = 'closed'; 

};

btnFechar.onclick = function(e) { 
e.preventDefault(); // Fechando o WebSocket. 
socket.close(); return false; 
};

};

