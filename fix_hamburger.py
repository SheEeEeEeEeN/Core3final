import os
import re

c3 = """    document.getElementById('hamburger').addEventListener('click', () => {
        const sidebar = document.getElementById('sidebar');
        const content = document.getElementById('mainContent');
        if (window.innerWidth > 768) { 
            sidebar.classList.toggle('collapsed'); 
            content.classList.toggle('expanded'); 
        } else {
            sidebar.classList.toggle('show');
            content.classList.toggle('mobile-expanded');
        }
    });"""

for path in ['c:/xampp/htdocs/core3/Archive.php', 'c:/xampp/htdocs/core3/Archive_CRM.php', 'c:/xampp/htdocs/core3/activity-log.php']:
    with open(path, 'r', encoding='utf-8') as f:
        content = f.read()

    # handle both single and double quote variants
    content = re.sub(r'document\.getElementById\(([\'"])hamburger\1\)\.addEventListener\(([\'"])click\2,\s*(function\s*\(\)|=>|=>\s*\{).*?\}\);', c3, content, flags=re.DOTALL|re.IGNORECASE)

    # specifically for function () {
    content = re.sub(r'document\.getElementById\("hamburger"\)\.addEventListener\("click",\s*function\s*\(\)\s*\{.*?\}\);', c3, content, flags=re.DOTALL)
    
    with open(path, 'w', encoding='utf-8') as f:
        f.write(content)
