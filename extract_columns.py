import os
import pandas as pd

# Set directories using raw strings
input_dir = r'C:\Users\Nabwire Jane\Downloads\NEW'
  # Replace with your input directory path
output_dir = r"C:\Users\Nabwire Jane\Documents\SCHOOLS2\NEW"  # Replace with your output directory path

# Ensure the output directory exists
os.makedirs(output_dir, exist_ok=True)

# List all .xls files in the input directory
files = [f for f in os.listdir(input_dir) if f.endswith('.xls')]

# Loop through each .xls file
for file in files:
    file_path = os.path.join(input_dir, file)
    
    # Define output file path with .xlsx extension
    extracted_file_path = os.path.join(output_dir, file.replace('.xls', '_extracted.xlsx'))
    
    try:
        # Load the .xls file and extract columns C, D, and F
        excel_data = pd.read_excel(file_path, usecols=[2, 3, 5], engine='xlrd')
        
        # Save the extracted columns to a new .xlsx file
        excel_data.to_excel(extracted_file_path, index=False, engine='openpyxl')
        
        print(f"Extracted columns C, D, F and saved to: {extracted_file_path}")

    except Exception as e:
        print(f"Failed to process {file}: {e}")

print("Extraction completed!")
