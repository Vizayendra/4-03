from flask import Flask

app = Flask(__name__)

@app.route('/')
def home():
    return "Hello, World! This is my first Flask app."

if __name__ == '__main__':
    app.run(debug=True)

    from flask import Flask, request, render_template

app = Flask(__name__)

from flask import Flask, request, render_template

app = Flask(__name__)

from flask import Flask, request, render_template_string

app = Flask(__name__)

@app.route('/')
def home():
    return '''
    <h1>Welcome!</h1>
    <p><a href="/register">Register</a> | <a href="/login">Login</a></p>
    '''

@app.route('/register', methods=['GET', 'POST'])
def register():
    if request.method == 'POST':
        username = request.form.get('username')
        email = request.form.get('email')
        return f"<h2>Thanks for registering, {username}!</h2><p>Your email is {email}.</p>"
    return '''
    <h1>Register</h1>
    <form method="POST">
        Username: <input type="text" name="username" required><br><br>
        Email: <input type="email" name="email" required><br><br>
        <button type="submit">Register</button>
    </form>
    <p><a href="/">Back to Home</a></p>
    '''

@app.route('/login', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        username = request.form.get('username')
        password = request.form.get('password')
        # Here you would normally check username/password in a database
        return f"<h2>Welcome back, {username}!</h2>"
    return '''
    <h1>Login</h1>
    <form method="POST">
        Username: <input type="text" name="username" required><br><br>
        Password: <input type="password" name="password" required><br><br>
        <button type="submit">Login</button>
    </form>
    <p><a href="/">Back to Home</a></p>
    '''

if __name__ == '__main__':
    app.run(debug=True)


