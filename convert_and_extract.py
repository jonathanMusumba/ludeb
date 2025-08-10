import os
import pandas as pd

# Set directories using raw strings
input_dir = r"G:\New folder\APPLOADS 2024\Extract"  # Replace with your input directory path
output_dir = r"C:\Users\Nabwire Jane\Documents\SCHOOLS2\extracts"  # Replace with your output directory path

# Ensure the output directory exists
os.makedirs(output_dir, exist_ok=True)

# List all .xls files in the input directory
files = [f for f in os.listdir(input_dir) if f.endswith('.xls')]

# Loop through each .xls file
for file in files:
    file_path = os.path.join(input_dir, file)
    
    # Define output file path with .xlsx extension
    output_file_path = os.path.join(output_dir, file.replace('.xls', '.xlsx'))
    
    try:
        # Load the .xls file and convert to .xlsx
        excel_data = pd.read_excel(file_path, engine='xlrd')
        excel_data.to_excel(output_file_path, index=False, engine='openpyxl')
        print(f"Converted: {file} to {output_file_path}")

        # Now extract columns C, D, and F from the converted .xlsx file
        extracted_file_path = os.path.join(output_dir, file.replace('.xls', '_extracted.xlsx'))
        
        # Read only the columns C, D, and F
        extracted_data = pd.read_excel(output_file_path, usecols=[2, 3, 5], engine='openpyxl')
        extracted_data.to_excel(extracted_file_path, index=False)
        
        print(f"Extracted columns C, D, F and saved to: {extracted_file_path}")

    except Exception as e:
        print(f"Failed to process {file}: {e}")

print("Conversion and extraction completed!")
