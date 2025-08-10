import os
import pandas as pd

# Set directories using raw strings
input_dir = r"G:\New folder\APPLOADS 2024\Extract"  # Replace with your input directory path
output_dir = r"C:\Users\Nabwire Jane\Documents\SCHOOLS2\extracts"  # Replace with your output directory path

# Ensure the output directory exists
os.makedirs(output_dir, exist_ok=True)

# List all Excel files in the input directory
files = [f for f in os.listdir(input_dir) if f.endswith('.xls')]

if not files:
    print("No Excel files found in the input directory.")

# Loop through each file and extract data from columns C, D, and F
for file in files:
    print(f"Processing file: {file}")
    # Load the Excel file
    file_path = os.path.join(input_dir, file)
    
    try:
        # Read only the columns C, D, and F by specifying their column indices (C=2, D=3, F=5)
        excel_data = pd.read_excel(file_path, usecols=[2, 3, 5])  # Columns C, D, F
        
        # Save the extracted columns to a new Excel file with the same name
        output_file_path = os.path.join(output_dir, file)
        
        # Save the extracted columns
        excel_data.to_excel(output_file_path, index=False)

        print(f"Processed and saved: {output_file_path}")
    except Exception as e:
        print(f"Failed to process {file}: {e}")

print("Extraction and saving completed!")
