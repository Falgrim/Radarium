import os
import re

directory = r'c:\Users\falgr\Documents\BimPro\radarium_gitlab\app\MoonShine\Resources'

def process_file(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    # 1. Update saveFilterState
    if 'protected bool $saveFilterState' in content:
        content = re.sub(r'protected\s+bool\s+\$saveFilterState\s*=\s*(true|false)\s*;', r'protected bool $saveFilterState = false;', content)
    else:
        content = re.sub(r'(class\s+\w+\s+extends\s+\w+\s*\{)', r'\1\n    protected bool $saveFilterState = false;\n', content)

    # 2. Add nullable() to Select, Enum, BelongsTo in filters()
    filters_match = re.search(r'public\s+function\s+filters\s*\(\)\s*(?::\s*array\s*)?\{', content)
    if filters_match:
        start_idx = filters_match.end()
        brace_count = 1
        end_idx = start_idx
        for i in range(start_idx, len(content)):
            if content[i] == '{':
                brace_count += 1
            elif content[i] == '}':
                brace_count -= 1
            if brace_count == 0:
                end_idx = i
                break

        filters_body = content[start_idx:end_idx]
        
        new_body = ""
        i = 0
        while i < len(filters_body):
            match = re.match(r'(Select|BelongsTo|Enum)::make\b', filters_body[i:])
            if match:
                word = match.group(0)
                i += len(word)
                
                p_count = 0
                b_count = 0
                c_count = 0
                in_str = False
                str_char = ''
                in_comment = False
                
                segment = ""
                # Parse until we hit comma, semicolon, or close bracket
                while i < len(filters_body):
                    c = filters_body[i]
                    if in_comment:
                        segment += c
                        i += 1
                        if c == '\n':
                            in_comment = False
                        continue
                    
                    if in_str:
                        segment += c
                        if c == '\\':
                            i += 1
                            if i < len(filters_body):
                                segment += filters_body[i]
                                i += 1
                            continue
                        if c == str_char:
                            in_str = False
                        i += 1
                        continue
                    
                    if c in ("'", '"'):
                        in_str = True
                        str_char = c
                        segment += c
                        i += 1
                        continue
                        
                    if c == '/' and i + 1 < len(filters_body) and filters_body[i+1] == '/':
                        in_comment = True
                        segment += c
                        i += 1
                        continue
                        
                    if c == '(':
                        p_count += 1
                    elif c == ')':
                        p_count -= 1
                    elif c == '[':
                        b_count += 1
                    elif c == ']':
                        b_count -= 1
                    elif c == '{':
                        c_count += 1
                    elif c == '}':
                        c_count -= 1
                        
                    if p_count == 0 and b_count == 0 and c_count == 0:
                        if c == ',' or c == ';':
                            if '->nullable(' not in segment and '->multiple(' not in segment:
                                segment += "->nullable()"
                            segment += c
                            i += 1
                            break
                        
                    if b_count < 0:
                        b_count = 0
                        if '->nullable(' not in segment and '->multiple(' not in segment:
                            segment += "->nullable()"
                        break

                    segment += c
                    i += 1
                
                new_body += word + segment
            else:
                new_body += filters_body[i]
                i += 1
                
        content = content[:start_idx] + new_body + content[end_idx:]

    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)

for filename in os.listdir(directory):
    if filename.endswith('.php'):
        process_file(os.path.join(directory, filename))

print("Modified files for filters nullable() and saveFilterState")
