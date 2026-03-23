CREATE TABLE user_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_user_setting (account_id, setting_key),

    FOREIGN KEY (account_id) REFERENCES accounts(accountid)
    ON DELETE CASCADE
);