import re

with open('admin_completed.php', 'r', encoding='utf-8') as f:
    admin = f.read()

# Extract from "<style>" down to "</header>"
start_marker = "    <style>"
end_marker = "        </header>"

s_idx = admin.find(start_marker)
e_idx = admin.find(end_marker) + len(end_marker)

header_block = admin[s_idx:e_idx]

# Wait, the active link in sidebar should be BI & Analytics
header_block = header_block.replace(
    '<a href="admin_completed.php" class="active"><i class="bi bi-check-circle-fill"></i> Completed Trans.</a>',
    '<a href="admin_completed.php" class=""><i class="bi bi-check-circle-fill"></i> Completed Trans.</a>'
)
header_block = header_block.replace(
    '<a href="BIFA.php" class=""><i class="bi bi-graph-up"></i> BI & Analytics</a>',
    '<a href="BIFA.php" class="active"><i class="bi bi-graph-up"></i> BI & Analytics</a>'
)
# Change the title in top header
header_block = header_block.replace(
    '<h5 class="fw-bold mb-0 text-primary">✅ Completed Deliveries</h5>',
    '<h5 class="fw-bold mb-0 text-primary">Freight Analytics</h5>'
)

# Now read BIFA.php
with open('BIFA.php', 'r', encoding='utf-8') as f:
    bifa = f.read()

# In BIFA.php we want to replace from "  <style>\n    :root {" down to just before "      <div class=\"col-lg-8\">"
s_bifa = bifa.find("  <style>\n    :root {\n      <div class=\"col-lg-8\">")

if s_bifa != -1:
    before = bifa[:s_bifa]
    after = bifa[s_bifa + len("  <style>\n    :root {\n"):]
    
    # insert the row div
    row_div = "\n<div class=\"row g-3 mb-4\">\n"
    
    new_bifa = before + header_block + row_div + after
    
    with open('BIFA.php', 'w', encoding='utf-8') as f:
        f.write(new_bifa)
    print("BIFA.php restored successfully!")
else:
    print("Could not find the target string in BIFA.php")
