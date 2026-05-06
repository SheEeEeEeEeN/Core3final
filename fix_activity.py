import re

with open('activity-log.php', 'r', encoding='utf-8') as f:
    content = f.read()

# First conflict
start1 = "<<<<<<< HEAD\n"
mid1 = "=======\n"
end1 = ">>>>>>> 83d39d8a6f3153cee1a9566f6e29044bf30ee3ff\n"

while True:
    idx_s1 = content.find(start1)
    if idx_s1 == -1:
        break
    idx_m1 = content.find(mid1, idx_s1)
    idx_e1 = content.find(end1, idx_m1) + len(end1)
    
    # Check if there's an actual conflict block matched
    if idx_m1 != -1 and idx_e1 > len(end1) - 1:
        # Keep HEAD
        head_block1 = content[idx_s1 + len(start1) : idx_m1]
        content = content[:idx_s1] + head_block1 + content[idx_e1:]
    else:
        break

with open('activity-log.php', 'w', encoding='utf-8') as f:
    f.write(content)
print("Fixed activity-log.php conflicts by taking HEAD.")
