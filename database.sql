CREATE DATABASE genius_lab;

USE genius_lab;



CREATE TABLE users (

    id INT AUTO_INCREMENT PRIMARY KEY,

    surname VARCHAR(100) NOT NULL,

    othernames VARCHAR(100) NOT NULL,

    dob DATE NOT NULL,

    gender VARCHAR(20) NOT NULL,

    email VARCHAR(150) NOT NULL UNIQUE,

    username VARCHAR(100) NOT NULL UNIQUE,

    password VARCHAR(255) NOT NULL,

    xp INT DEFAULT 0,

    level INT DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);




CREATE TABLE posts (

    id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL,

    content TEXT NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id)
    REFERENCES users(id)
    ON DELETE CASCADE

);



CREATE TABLE comments (

    id INT AUTO_INCREMENT PRIMARY KEY,

    post_id INT NOT NULL,

    user_id INT NOT NULL,

    comment TEXT NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (post_id)
    REFERENCES posts(id)
    ON DELETE CASCADE,

    FOREIGN KEY (user_id)
    REFERENCES users(id)
    ON DELETE CASCADE

);



CREATE TABLE xp_logs (

    id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL,

    xp_earned INT NOT NULL,

    reason VARCHAR(255),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id)
    REFERENCES users(id)
    ON DELETE CASCADE

);