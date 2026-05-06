import re

with open('admin_reports.php', 'r', encoding='utf-8') as f:
    content = f.read()

# First conflict
start1 = "<<<<<<< HEAD\n"
mid1 = "=======\n"
end1 = ">>>>>>> 83d39d8a6f3153cee1a9566f6e29044bf30ee3ff\n"

idx_s1 = content.find(start1)
idx_m1 = content.find(mid1, idx_s1)
idx_e1 = content.find(end1, idx_m1) + len(end1)

# we only keep between start1 and mid1
head_block1 = content[idx_s1 + len(start1) : idx_m1]
content = content[:idx_s1] + head_block1 + content[idx_e1:]

# Second conflict
idx_s2 = content.find(start1)
idx_m2 = content.find(mid1, idx_s2)
idx_e2 = content.find(end1, idx_m2) + len(end1)

head_block2 = content[idx_s2 + len(start1) : idx_m2]
content = content[:idx_s2] + head_block2 + content[idx_e2:]

with open('admin_reports.php', 'w', encoding='utf-8') as f:
    f.write(content)
print("Fixed admin_reports.php conflicts by taking HEAD.")
