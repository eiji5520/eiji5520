from instagrapi import Client
import argparse
import json
from pathlib import Path

def main(username: str, password: str):
    cl = Client()
    cl.login(username, password)
    session = cl.get_settings()
    with open(Path(__file__).resolve().parent / 'session.json', 'w') as f:
        json.dump(session, f)

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Login to Instagram')
    parser.add_argument('username')
    parser.add_argument('password')
    args = parser.parse_args()
    main(args.username, args.password)
