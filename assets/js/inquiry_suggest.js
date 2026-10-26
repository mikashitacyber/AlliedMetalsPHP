// Reads what the customer typed and suggests a matching inquiry type.
// To add or change keywords, just edit the list below — nothing else
// in this file needs to change.
const keywordMap = [
    { kw: ['stainless', 'fabricat', 'weld', 'counter', 'sink', 'hood', 'rack', 'worktable'], label: 'Custom stainless steel fabrication' },
    { kw: ['quote', 'equipment', 'fridge', 'oven', 'refrigerat', 'chiller', 'freezer', 'warewash'], label: 'Equipment inquiry' },
    { kw: ['install', 'commission'], label: 'Installation or commissioning' },
    { kw: ['repair', 'broken', 'maintenance', 'pms', 'service', 'not working', 'leak'], label: 'Parts, repair, or preventive maintenance' },
    { kw: ['new kitchen', 'design', 'layout', 'renovat', 'project', 'floor plan', 'blueprint'], label: 'New project or consultation' },
];

function suggestDept() {
    const messageBox = document.getElementById('inq-msg');
    const box = document.getElementById('suggest-box');
    if (!messageBox || !box) return;

    const val = messageBox.value.toLowerCase();
    if (val.trim().length < 8) {
        box.style.display = 'none';
        return;
    }

    const match = keywordMap.find(k => k.kw.some(word => val.includes(word)));
    if (match) {
        document.getElementById('suggest-label').textContent = match.label;
        box.style.display = 'flex';
        box.dataset.suggest = match.label;
    } else {
        box.style.display = 'none';
    }
}

function acceptSuggestion() {
    const box = document.getElementById('suggest-box');
    const select = document.getElementById('inq-type');
    if (!box || !select) return;
    [...select.options].forEach(option => {
        if (option.text === box.dataset.suggest) select.value = option.text;
    });
}
