USE taster_db;

CREATE TABLE IF NOT EXISTS Tags (
    ID VARCHAR(255) PRIMARY KEY,
    Name VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS ReviewTags (
    ReviewID VARCHAR(255) NOT NULL,
    TagID VARCHAR(255) NOT NULL,
    PRIMARY KEY (ReviewID, TagID),
    FOREIGN KEY (ReviewID) REFERENCES Reviews(ID) ON DELETE CASCADE,
    FOREIGN KEY (TagID) REFERENCES Tags(ID) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS ReviewUpvotes (
    ReviewID VARCHAR(255) NOT NULL,
    AccountID VARCHAR(255) NOT NULL,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ReviewID, AccountID),
    FOREIGN KEY (ReviewID) REFERENCES Reviews(ID) ON DELETE CASCADE,
    FOREIGN KEY (AccountID) REFERENCES Accounts(ID) ON DELETE CASCADE
);

INSERT IGNORE INTO Tags (ID, Name) VALUES
    ('tag-hoppy', 'hoppy'),
    ('tag-bitter', 'bitter'),
    ('tag-sweet', 'sweet'),
    ('tag-malty', 'malty'),
    ('tag-fruity', 'fruity'),
    ('tag-citrusy', 'citrusy'),
    ('tag-roasty', 'roasty'),
    ('tag-smooth', 'smooth'),
    ('tag-crisp', 'crisp'),
    ('tag-sour', 'sour'),
    ('tag-spicy', 'spicy');