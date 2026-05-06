import re

with open('BIFA.php', 'r', encoding='utf-8') as f:
    content = f.read()

# The conflict is between lines 153 and 374.
start_marker = "<<<<<<< HEAD\n"
mid_marker = "=======\n"
end_marker = ">>>>>>> 83d39d8a6f3153cee1a9566f6e29044bf30ee3ff\n"

start_idx = content.find(start_marker)
mid_idx = content.find(mid_marker)
end_idx = content.find(end_marker) + len(end_marker)

if start_idx != -1 and mid_idx != -1 and content.find(end_marker) != -1:
    # Keep the HEAD block (which is start_idx + len(start_marker) up to mid_idx)
    # The HEAD block ends with "<div class="row g-3 mb-4">\n" before "======="
    head_block = content[start_idx + len(start_marker):mid_idx]
    
    # Replace the whole conflicted region with just the HEAD block
    new_content = content[:start_idx] + head_block + content[end_idx:]
    
    with open('BIFA.php', 'w', encoding='utf-8') as f:
        f.write(new_content)
    print("Fixed BIFA.php")
else:
    print("Conflict markers not found in BIFA.php")
