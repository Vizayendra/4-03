import os

USER_FILE = "users.txt"

def load_users():
    users = {}
    if os.path.exists(USER_FILE):
        with open(USER_FILE, "r") as file:
            for line in file:
                line = line.strip()
                if line:
                    username, password = line.split(",")
                    users[username] = password
    return users

def save_user(username, password):
    with open(USER_FILE, "a") as file:
        file.write(f"{username},{password}\n")

def signup():
    users = load_users()
    username = input("Choose a username: ")
    if username in users:
        print("Username already exists. Try another.")
        return
    password = input("Choose a password: ")
    confirm = input("Confirm password: ")
    if password != confirm:
        print("Passwords do not match.")
        return
    save_user(username, password)
    print("Signup successful!")

def login():
    users = load_users()
    username = input("Enter username: ")
    password = input("Enter password: ")
    if username in users and users[username] == password:
        print(f"Welcome back, {username}!")
    else:
        print("Incorrect username or password.")

def main():
    while True:
        print("\n=== Login System ===")
        print("1. Sign up")
        print("2. Log in")
        print("3. Exit")
        choice = input("Choose an option (1-3): ")

        if choice == "1":
            signup()
        elif choice == "2":
            login()
        elif choice == "3":
            print("Goodbye!")
            break
        else:
            print("Invalid choice.")

if __name__ == "__main__":
    main()
