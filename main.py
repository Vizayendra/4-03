from flask import Flask, render_template, request, redirect, url_for

app = Flask(__name__)

# Temporary storage for online meetings
meetings = [
    {
        'title': 'Math Revision Group',
        'time': '2025-05-10 5:00 PM',
        'link': 'https://zoom.us/j/123456789'
    },
    {
        'title': 'Science Study Session',
        'time': '2025-05-12 4:30 PM',
        'link': 'https://meet.google.com/xyz-abc-def'
    }
]
@app.route('/meetings')
def view_meetings():
    return render_template('view_meetings.html', meetings=meetings)