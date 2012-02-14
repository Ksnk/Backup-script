DROP TABLE IF EXISTS articles,users;
CREATE TABLE articles (
  id INT NOT NULL auto_increment PRIMARY KEY,
  content TEXT,
  user_id INT NOT NULL
)ENGINE=InnoDB;
CREATE INDEX articles_user_idx ON articles(user_id);
CREATE TABLE users (
  id INT NOT NULL auto_increment PRIMARY KEY,
  name VARCHAR(255)
)ENGINE=InnoDB;
ALTER TABLE articles ADD FOREIGN KEY (user_id) REFERENCES users(id);
INSERT INTO users VALUES (1, 'User 1');
INSERT INTO articles VALUES (1, 'article 1', 1);