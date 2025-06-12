import argparse


def main():
    parser = argparse.ArgumentParser(description='Run Instagram bot actions')
    parser.add_argument('--follow', nargs='*')
    parser.add_argument('--like', nargs='*')
    args = parser.parse_args()
    # Placeholder logic
    print('Bot actions:', args)

if __name__ == '__main__':
    main()
