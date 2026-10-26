// Hides the Department field when adding an HR account,
// since a department only applies to Staff.
document.addEventListener('DOMContentLoaded', () => {
    const roleSelect = document.getElementById('role-select');
    const departmentField = document.getElementById('department-field');
    if (!roleSelect || !departmentField) return;

    function updateFields() {
        departmentField.style.display = roleSelect.value === 'Staff' ? 'block' : 'none';
    }

    roleSelect.addEventListener('change', updateFields);
    updateFields();
});
