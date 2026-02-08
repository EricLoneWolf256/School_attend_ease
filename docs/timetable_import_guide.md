# Timetable Import System Documentation

## Overview
The timetable import system allows administrators to bulk upload lecture schedules from Excel/CSV files. Once imported, these timetables are automatically visible to both students and lecturers.

## How It Works

### 1. Admin Upload Process
1. **Navigate to**: Admin Dashboard → Timetable
2. **Click**: "Import Timetable" button
3. **Upload**: CSV file with lecture data
4. **System**: Validates data and creates lectures automatically

### 2. Required CSV Format
Your CSV file must contain these columns (case-insensitive):

| Column | Format | Example | Description |
|--------|--------|---------|-------------|
| course_code | Text | "CS101" | Must match existing course codes |
| lecturer_email | Email | "john@university.edu" | Must match existing lecturer emails |
| date | YYYY-MM-DD | "2024-02-01" | Date of the lecture |
| start_time | HH:MM | "09:00" | Start time in 24-hour format |
| end_time | HH:MM | "10:30" | End time in 24-hour format |
| title | Text | "Introduction to Programming" | Lecture title/description |

### 3. Sample CSV File
```csv
course_code,lecturer_email,date,start_time,end_time,title
CS101,lecturer1@example.com,2024-02-01,09:00,10:30,Introduction to Programming
CS101,lecturer1@example.com,2024-02-03,09:00,10:30,Data Types and Variables
CS102,lecturer2@example.com,2024-02-01,11:00,12:30,Web Development Basics
CS102,lecturer2@example.com,2024-02-05,11:00,12:30,CSS and Styling
IT201,lecturer3@example.com,2024-02-02,14:00,15:30,Network Fundamentals
```

### 4. Validation Rules
The system validates:
- ✅ **Course exists**: course_code must match existing courses
- ✅ **Lecturer exists**: lecturer_email must match existing lecturers
- ✅ **Date format**: Must be YYYY-MM-DD
- ✅ **Time format**: Must be HH:MM (24-hour)
- ✅ **No duplicates**: Same course, lecturer, date, and time cannot repeat
- ✅ **Required fields**: All columns must have values

### 5. Import Results
After import, you'll see:
- **Success count**: Number of lectures created
- **Skipped count**: Rows with errors
- **Error messages**: First few errors (if any)

## Visibility to Users

### Students Can See:
- **Their enrolled courses' lectures** on their timetable
- **Weekly view** of upcoming lectures
- **Lecture details**: time, location, lecturer
- **Attendance status** for each lecture

### Lecturers Can See:
- **Their assigned courses' lectures** on their timetable
- **Weekly view** of their teaching schedule
- **Student enrollment** numbers
- **Attendance tracking** for each lecture
- **Active lecture codes** for attendance marking

## Access Points

### Admin
- **URL**: `/ghost/admin/timetable.php`
- **Features**: Import, view, edit, delete lectures
- **Template**: Download CSV template from import modal

### Students
- **URL**: `/ghost/student/timetable.php`
- **Features**: View weekly schedule for enrolled courses
- **Auto-updates**: Shows newly imported lectures immediately

### Lecturers
- **URL**: `/ghost/lecturer/timetable.php`
- **Features**: View weekly teaching schedule
- **Auto-updates**: Shows newly imported lectures immediately

## Benefits

1. **Bulk Upload**: Import hundreds of lectures at once
2. **Automatic Distribution**: No need to manually notify users
3. **Real-time Updates**: Changes appear immediately for all users
4. **Validation**: Prevents invalid data entry
5. **Error Reporting**: Clear feedback on import issues

## Tips for Success

1. **Prepare Data First**: Ensure all courses and lecturers exist in the system
2. **Use Template**: Download and use the provided CSV template
3. **Check Formats**: Verify date (YYYY-MM-DD) and time (HH:MM) formats
4. **Test Small**: Import a small batch first to test your data
5. **Review Results**: Check import results for any skipped rows

## Troubleshooting

### Common Issues:
- **Course not found**: Check course_code matches exactly
- **Lecturer not found**: Verify lecturer_email is correct
- **Invalid date**: Use YYYY-MM-DD format (e.g., 2024-02-01)
- **Invalid time**: Use 24-hour HH:MM format (e.g., 09:00)
- **Duplicate entries**: Same lecture already exists

### Solutions:
1. Verify all courses exist in Admin → Courses
2. Verify all lecturers exist in Admin → Lecturers
3. Use the provided CSV template
4. Check for data entry errors
5. Review import error messages carefully
