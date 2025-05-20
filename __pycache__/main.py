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

# View all meetings
@app.route('/meetings')
def view_meetings():
    return render_template('view_meetings.html', meetings=meetings)

# Schedule new meetings
@app.route('/schedule_meeting', methods=['GET', 'POST'])
def schedule_meeting():
    if request.method == 'POST':
        title = request.form['title']
        time = request.form['time']
        link = request.form['link']
        # Add new meeting to the list
        meetings.append({'title': title, 'time': time, 'link': link})
        # Return to meeting list
        return redirect(url_for('view_meetings'))
    # Show the place to schedule new meetings
    return render_template('schedule_meeting.html')


# Temporary storage for groups
groups = []

# View all the groups
@app.route('/groups')
def view_groups():
    return render_template('view_groups.html', groups=groups)

# Simulating a user
current_user = "Student"  

# Join a group
@app.route('/join_group/<int:group_id>')
def join_group(group_id):
    if current_user not in groups[group_id]['members']:
        groups[group_id]['members'].append(current_user)
    return redirect(url_for('view_groups'))

# Leave the group
@app.route('/leave_group/<int:group_id>')
def leave_group(group_id):
    if current_user in groups[group_id]['members']:
        groups[group_id]['members'].remove(current_user)
    return redirect(url_for('view_groups'))


@app.route('/create_group', methods=['GET', 'POST'])
def create_group():
    if request.method == 'POST':
        name = request.form['name']
        subject = request.form['subject']
        meeting_link = request.form['meeting_link']
        description = request.form['description']
        groups.append({
            'name': name,
            'subject': subject,
            'meeting_link': meeting_link,
            'description': description,
            'members': []
        })
        return redirect(url_for('view_groups'))
    return render_template('create_group.html')

if __name__ == '__main__':
    app.run(debug=True)
