const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const path = require('path');
const mysql = require('mysql2');

const app = express();
const server = http.createServer(app);

// CORS LIBERADO PARA O PHP
const io = socketIo(server, {
    cors: {
        origin: "*",
        methods: ["GET", "POST"]
    }
});

// 🔌 Conexão com o banco MySQL
const db = mysql.createConnection({
    host: 'localhost',
    user: 'root',
    password: '',
    database: 'autive_db'
});

db.connect((err) => {
    if (err) {
        console.error('❌ Erro ao conectar ao MySQL:', err);
        return;
    }
    console.log('✅ Conectado ao MySQL');
});

// 🧱 Middleware para arquivos estáticos
app.use(express.static(path.join(__dirname, 'public')));

// 💬 Lógica do Socket.IO
io.on('connection', (socket) => {
    console.log('👤 Novo usuário conectado');

    // Enviar histórico
    db.query('SELECT * FROM mensagens ORDER BY data_envio ASC', (err, results) => {
        if (!err && results.length > 0) {
            socket.emit('historico', results);
        }
    });

    socket.on('join', (username) => {
        socket.username = username;
        io.emit('userJoined', username);
    });

    socket.on('chatMessage', (data) => {
        db.query(
            'INSERT INTO mensagens (usuario, mensagem) VALUES (?, ?)',
            [data.username, data.message],
            (err) => {
                if (err) console.error('Erro ao salvar mensagem:', err);
            }
        );

        io.emit('chatMessage', data);
    });

    socket.on('disconnect', () => {
        if (socket.username) {
            io.emit('userLeft', socket.username);
        }
    });
});

server.listen(3000, () => {
    console.log('🚀 Servidor rodando em http://localhost:3000');
});
