import socket
import netifaces as ni
import os

def get_ipv4_addresses():
    addresses = {}
    interfaces = ni.interfaces()
    for interface in interfaces:
        addrs = ni.ifaddresses(interface)
        ipv4_info = addrs.get(socket.AF_INET, [])
        if ipv4_info:
            addresses[interface] = ipv4_info[0]['addr']
    return addresses

def start_php_server(ip_address):
    os.system(f"php -S {ip_address}:11111")

def main():
    response = input("サーバーを起動しますか？ [y/n]: ")
    if response.lower() == 'y':
        ipv4_addresses = get_ipv4_addresses()
        for interface, ip in ipv4_addresses.items():
            if ip != "127.0.0.1":
                print(f"サーバーを {ip} で起動します。")
                start_php_server(ip)
                break
        else:
            print("127.0.0.1 以外のIPv4アドレスが見つかりませんでした。")
    else:
        print("サーバーの起動をキャンセルしました。")

if __name__ == "__main__":
    main()
